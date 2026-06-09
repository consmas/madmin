<?php

namespace Modules\AI\app\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AI\Entities\CustomerIntentLog;
use Throwable;

class AiShoppingAssistantService
{
    public function __construct(
        private readonly AiConversationService $conversationService,
        private readonly AiProductSearchService $productSearchService,
        private readonly AiOpenAIService $openAIService,
    ) {}

    public function respond(Request $request): array
    {
        $conversation = $this->conversationService->createOrResolveConversation($request);
        $message = $request->string('message')->toString();
        $this->conversationService->storeUserMessage($conversation, $message, ['channel' => $request->input('channel')]);

        $classification = $this->openAIService->classifyIntent($message);
        $products = [];
        $liveDataAvailable = true;
        if (in_array($classification['intent'], ['product_search', 'product_recommendation', 'price_question', 'category_guidance'], true)) {
            $searchResult = $this->productSearchService->search(
                $message,
                $request->input('context.filters', []),
                8,
                $request,
                $conversation->id,
            );
            $products = $searchResult['results'];
            $liveDataAvailable = $searchResult['live_data_available'];
        }

        $this->logIntent($request, $conversation->id, $classification);
        $aiResponse = $liveDataAvailable
            ? $this->openAIService->generateAssistantResponse([
                'message' => $message,
                'intent' => $classification['intent'],
                'products' => $products,
                'options' => ['ai_conversation_id' => $conversation->id],
            ])
            : [
                'success' => false,
                'model' => null,
                'latency_ms' => null,
                'usage' => [],
            ];

        $content = $aiResponse['success']
            ? $aiResponse['content']
            : $this->fallbackResponse($classification['intent'], $products, $liveDataAvailable);

        $this->conversationService->storeAssistantMessage($conversation, $content, [
            'model' => $aiResponse['model'],
            'latency_ms' => $aiResponse['latency_ms'],
            ...$aiResponse['usage'],
            'provider_status' => $aiResponse['success'] ? 'success' : 'fallback',
        ]);

        return [
            'conversation_uuid' => $conversation->uuid,
            'message' => $content,
            'intent' => $classification['intent'],
            'confidence' => $classification['confidence'],
            'products' => $products,
            'suggested_actions' => $this->suggestedActions($products),
            'requires_human_review' => false,
            'safety_note' => 'Confirm prices, stock, delivery availability, and material quantities before purchase.',
        ];
    }

    private function fallbackResponse(string $intent, array $products, bool $liveDataAvailable): string
    {
        if (! $liveDataAvailable) {
            return 'I cannot access live ConsMas product data right now, so I cannot safely recommend products or quote prices. Please try again or contact the ConsMas sales team.';
        }

        if ($products !== []) {
            return 'I found available ConsMas catalog options matching your request. Review the products below and confirm current stock, delivery availability, and quantities before checkout.';
        }

        return match ($intent) {
            'order_tracking_guidance' => 'Please use the order tracking page or contact ConsMas support with your order reference.',
            'delivery_question' => 'Delivery availability and final charges depend on the selected product, vendor, and destination. Please select a product and confirm your delivery location.',
            default => 'I could not access a matching live product result right now. Please refine the product name or contact the ConsMas sales team.',
        };
    }

    private function suggestedActions(array $products): array
    {
        $actions = $products !== [] ? [['label' => 'View products', 'type' => 'view_products']] : [];
        $actions[] = ['label' => 'Speak to sales', 'type' => 'contact_sales'];

        return $actions;
    }

    private function logIntent(Request $request, int $conversationId, array $classification): void
    {
        try {
            $actor = $this->conversationService->getActorFromRequest($request);
            CustomerIntentLog::create([
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'ai_conversation_id' => $conversationId,
                'intent' => $classification['intent'],
                'location_text' => $request->input('context.location'),
                'budget_max' => $request->input('context.budget'),
                'extracted_entities' => $request->input('context', []),
                'confidence' => $classification['confidence'],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Customer intent logging failed.', ['error' => $exception->getMessage()]);
        }
    }
}
