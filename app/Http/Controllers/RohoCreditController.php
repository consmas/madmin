<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Traits\Processor;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RohoCreditController extends Controller
{
    use Processor;

    private PaymentRequest $payment;
    private $user;
    private string $baseUrl = 'http://165.232.104.182';
    private ?string $apiKey = null;
    private ?string $webhookSecret = null;
    private ?int $institutionId = null;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('roho_credit', 'payment_config');

        $values = null;
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        if ($values) {
            $this->apiKey = isset($values->api_key) ? trim((string) $values->api_key) : null;
            $this->webhookSecret = isset($values->webhook_signing_secret) ? trim((string) $values->webhook_signing_secret) : null;
            $this->institutionId = isset($values->institution_id) ? (int) $values->institution_id : null;
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function pay(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if (!$this->apiKey) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, [['message' => 'Roho Credit API key missing']]), 400);
        }

        $payer = json_decode($data['payer_information'], true);
        $customerName = $payer['name'] ?? 'Customer';
        $splitName = $this->splitName($customerName);

        // Redirect to Roho hosted checkout popup; they will create the financing application server-side.
        // Use the actual order reference when available so the popup pre-fills correctly.
        $orderId = $data['attribute_id'] ?? (string) $data['id'];
        $amountGhs = number_format((float) $data['payment_amount'], 2, '.', '');

        // Send only the keys the popup expects for prefill (keep them flat).
        $query = array_filter([
            'external_order_id' => $orderId,
            'order_id' => $orderId,
            'amount_ghs' => $amountGhs,
            'amount' => $amountGhs,
            'currency' => $data['currency_code'] ?? 'GHS',
            'customer_first_name' => $splitName['first'],
            'customer_last_name' => $splitName['last'],
            'customer_email' => $payer['email'] ?? null,
            'customer_phone' => $payer['phone'] ?? null,
            'customer_address' => $payer['address'] ?? null,
            'customer_city' => $payer['city'] ?? null,
            'customer_country' => $payer['country'] ?? 'GH',
            'institution_id' => $this->institutionId ?: 1,
            'payment_id' => $data['id'],
            'attribute' => $data['attribute'],
            'attribute_id' => $data['attribute_id'],
            'metadata' => json_encode([
                'payment_id' => $data['id'],
                'attribute' => $data['attribute'],
                'attribute_id' => $data['attribute_id'],
            ]),
        ], static fn ($value) => $value !== null && $value !== '');

        Log::info('roho-credit.init', [
            'payment_id' => $data['id'],
            'payload' => ['query' => $query],
        ]);

        $redirectUrl = $this->baseUrl . '/checkout/popup?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return redirect()->away($redirectUrl);
    }

    public function webhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $providedSignature = trim((string) $request->header('X-Roho-Signature'));

        // Try both webhook secret and API key (in case the platform signs with either).
        $secrets = array_filter([
            $this->webhookSecret,
            config('roho_credit.webhook_signing_secret'),
            $this->apiKey,
            config('roho_credit.api_key'),
        ], static fn ($value) => !empty($value));

        if (!empty($secrets)) {
            $isValid = false;
            foreach ($secrets as $secret) {
                $expectedSignature = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
                if ($providedSignature !== '' && hash_equals($expectedSignature, $providedSignature)) {
                    $isValid = true;
                    break;
                }
            }

            if (!$isValid) {
                Log::warning('roho-credit.webhook.signature_mismatch', [
                    'provided' => $providedSignature,
                    'secrets_checked' => count($secrets),
                ]);
                return response()->json(['message' => 'Invalid signature'], 400);
            }
        }

        $payload = $request->json()->all();
        $application = data_get($payload, 'financing_application', $payload);
        $paymentId = data_get($application, 'metadata.payment_id');
        $status = data_get($application, 'status');

        if ($paymentId) {
            $paymentData = $this->payment::where(['id' => $paymentId])->first();
            if ($paymentData && in_array($status, ['approved', 'active', 'funded', 'completed', 'paid'])) {
                $this->payment::where(['id' => $paymentId])->update([
                    'payment_method' => 'roho_credit',
                    'is_paid' => 1,
                    'transaction_id' => data_get($application, 'id'),
                ]);
                $paymentData = $this->payment::where(['id' => $paymentId])->first();
                if (isset($paymentData) && function_exists($paymentData->success_hook)) {
                    call_user_func($paymentData->success_hook, $paymentData);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function splitName(string $name): array
    {
        $parts = array_values(array_filter(explode(' ', $name)));
        $first = $parts[0] ?? 'Customer';
        $last = $parts[1] ?? ($parts[0] ?? 'Customer');

        return ['first' => $first, 'last' => $last];
    }
}
