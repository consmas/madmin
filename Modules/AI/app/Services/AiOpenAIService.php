<?php

namespace Modules\AI\app\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class AiOpenAIService
{
    public function __construct(private readonly AiAuditService $auditService) {}

    public function chat(array $messages, array $options = []): array
    {
        $startedAt = microtime(true);
        $model = $options['model'] ?? config('ai.default_model', 'gpt-4o-mini');

        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.2,
                'max_tokens' => $options['max_tokens'] ?? config('ai.max_tokens', 700),
            ]);

            $usage = [
                'prompt_tokens' => $response->usage?->promptTokens,
                'completion_tokens' => $response->usage?->completionTokens,
                'total_tokens' => $response->usage?->totalTokens,
            ];
            $result = [
                'success' => true,
                'content' => $response->choices[0]->message->content ?? '',
                'model' => $response->model ?? $model,
                'usage' => $usage,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => null,
            ];

            $this->logCall($messages, $result, $options);

            return $result;
        } catch (Throwable $exception) {
            $result = [
                'success' => false,
                'content' => '',
                'model' => $model,
                'usage' => [],
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => 'AI provider unavailable.',
            ];

            $this->logCall($messages, $result, $options, $exception);

            return $result;
        }
    }

    public function classifyIntent(string $message): array
    {
        $message = mb_strtolower($message);
        $intents = [
            'order_tracking_guidance' => ['track', 'tracking', 'where is my order', 'order status'],
            'delivery_question' => ['delivery', 'deliver', 'shipping', 'arrival'],
            'price_question' => ['price', 'cost', 'cheap', 'affordable', 'budget'],
            'vendor_question' => ['vendor', 'supplier', 'store'],
            'category_guidance' => ['difference', 'which type', 'category', 'best type'],
            'product_recommendation' => ['recommend', 'suggest', 'best', 'option'],
            'product_search' => ['find', 'need', 'looking for', 'buy', 'cement', 'rod', 'roof', 'block', 'paint'],
        ];

        foreach ($intents as $intent => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($message, $needle)) {
                    return ['intent' => $intent, 'confidence' => 0.82];
                }
            }
        }

        return ['intent' => 'unknown', 'confidence' => 0.55];
    }

    public function generateAssistantResponse(array $context): array
    {
        $products = collect($context['products'] ?? [])->map(fn (array $product) => [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'store' => $product['store'],
            'stock_status' => $product['stock_status'],
        ])->values()->all();

        return $this->chat([
            [
                'role' => 'system',
                'content' => 'You are the read-only ConsMas shopping assistant. Use only the supplied live catalog data. Never invent products, prices, stock, delivery times, engineering quantities, credentials, or internal rules. Never claim to place orders or change carts. Keep the response concise and recommend manual next steps. Material estimates require contractor or quantity-surveyor confirmation.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'customer_message' => $this->auditService->redact($context['message'] ?? ''),
                    'intent' => $context['intent'] ?? 'unknown',
                    'live_products' => $products,
                ], JSON_UNESCAPED_SLASHES),
            ],
        ], $context['options'] ?? []);
    }

    private function logCall(array $messages, array $result, array $options, ?Throwable $exception = null): void
    {
        $this->auditService->logToolCall([
            'ai_conversation_id' => $options['ai_conversation_id'] ?? null,
            'ai_message_id' => $options['ai_message_id'] ?? null,
            'tool_name' => 'openai.chat',
            'input_hash' => hash('sha256', json_encode($messages)),
            'input_summary' => ['message_count' => count($messages), 'model' => $result['model']],
            'output_summary' => ['success' => $result['success'], 'usage' => $result['usage']],
            'status' => $result['success'] ? 'success' : 'failed',
            'error_message' => $exception?->getMessage(),
            'latency_ms' => $result['latency_ms'],
        ]);
    }
}
