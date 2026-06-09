<?php

namespace Modules\AI\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\AI\app\Http\Requests\Api\V1\AiChatRequest;
use Modules\AI\app\Http\Requests\Api\V1\AiProductSearchRequest;
use Modules\AI\app\Http\Requests\Api\V1\AiRecommendationEventRequest;
use Modules\AI\app\Services\AiConversationService;
use Modules\AI\app\Services\AiProductSearchService;
use Modules\AI\app\Services\AiShoppingAssistantService;
use Modules\AI\Entities\AiRecommendation;

class AiController extends Controller
{
    public function __construct(
        private readonly AiShoppingAssistantService $shoppingAssistantService,
        private readonly AiProductSearchService $productSearchService,
        private readonly AiConversationService $conversationService,
    ) {}

    public function chat(AiChatRequest $request): JsonResponse
    {
        abort_unless(config('ai.enable_ai_chat', true), 404);

        return response()->json($this->shoppingAssistantService->respond($request));
    }

    public function searchProducts(AiProductSearchRequest $request): JsonResponse
    {
        abort_unless(config('ai.enable_ai_search', true), 404);

        return response()->json($this->productSearchService->search(
            $request->string('query')->toString(),
            $request->input('filters', []),
            $request->integer('limit', 10),
            $request,
        ));
    }

    public function recommendationEvent(AiRecommendationEventRequest $request): JsonResponse
    {
        $actor = $this->conversationService->getActorFromRequest($request);
        $recommendation = null;

        if ($request->filled('recommendation_id') && $actor['id'] !== null) {
            $recommendation = AiRecommendation::query()
                ->whereKey($request->integer('recommendation_id'))
                ->where('actor_type', $actor['type'])
                ->where('actor_id', $actor['id'])
                ->first();
        }

        $recommendation ??= new AiRecommendation([
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'item_id' => $request->input('item_id'),
            'store_id' => $request->input('store_id'),
            'recommendation_type' => $request->input('recommendation_type', 'assistant'),
        ]);

        $recommendation->fill([
            'outcome' => $request->string('event')->toString(),
            'context' => $request->input('context', []),
        ])->save();

        return response()->json(['success' => true, 'recommendation_id' => $recommendation->id]);
    }
}
