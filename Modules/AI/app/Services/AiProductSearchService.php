<?php

namespace Modules\AI\app\Services;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\AI\Entities\AiSearchLog;
use Throwable;

class AiProductSearchService
{
    public function __construct(
        private readonly AiConversationService $conversationService,
        private readonly AiAuditService $auditService,
    ) {}

    public function search(string $searchTerm, array $filters = [], int $limit = 10, ?Request $request = null, ?int $conversationId = null): array
    {
        $startedAt = microtime(true);
        $term = trim($searchTerm);
        $like = '%'.$term.'%';
        $keywords = $this->keywords($term);

        $query = Item::query()
            ->active()
            ->with(['store:id,name,status,active', 'category:id,name', 'tags:id,tag'])
            ->where(function ($query) use ($keywords, $like) {
                $query->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like);

                foreach ($keywords as $keyword) {
                    $keywordLike = '%'.$keyword.'%';
                    $query->orWhere('name', 'like', $keywordLike)
                        ->orWhere('description', 'like', $keywordLike)
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $keywordLike))
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', $keywordLike))
                        ->orWhereHas('tags', fn ($tag) => $tag->where('tag', 'like', $keywordLike));
                }
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['store_id'] ?? null, fn ($query, $id) => $query->where('store_id', $id))
            ->when(isset($filters['min_price']), fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$like])
            ->orderByDesc('order_count')
            ->orderByDesc('avg_rating');

        try {
            $items = $query->limit($limit)->get();
        } catch (Throwable $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $this->auditService->logToolCall([
                'ai_conversation_id' => $conversationId,
                'tool_name' => 'product.search',
                'input_hash' => hash('sha256', $term.json_encode($filters)),
                'input_summary' => ['query' => $term, 'filters' => $filters, 'limit' => $limit],
                'output_summary' => ['result_count' => 0, 'live_data_available' => false],
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'latency_ms' => $latency,
            ]);

            Log::warning('AI product search failed.', ['error' => $exception->getMessage()]);

            return [
                'query' => $term,
                'results' => [],
                'result_count' => 0,
                'filters' => $filters,
                'search_log_id' => null,
                'latency_ms' => $latency,
                'live_data_available' => false,
                'notice' => 'Live product data is temporarily unavailable.',
            ];
        }

        $results = $items->map(fn (Item $item) => $this->normalize($item, $term))->values()->all();
        $latency = (int) round((microtime(true) - $startedAt) * 1000);
        $actor = $request ? $this->conversationService->getActorFromRequest($request) : ['type' => 'anonymous', 'id' => null];
        $log = $this->logSearch($actor, $term, $filters, $items->pluck('id')->all(), $latency);

        $this->auditService->logToolCall([
            'ai_conversation_id' => $conversationId,
            'tool_name' => 'product.search',
            'input_hash' => hash('sha256', $term.json_encode($filters)),
            'input_summary' => ['query' => $term, 'filters' => $filters, 'limit' => $limit],
            'output_summary' => ['result_count' => count($results), 'item_ids' => $items->pluck('id')->all()],
            'latency_ms' => $latency,
        ]);

        return [
            'query' => $term,
            'results' => $results,
            'result_count' => count($results),
            'filters' => $filters,
            'search_log_id' => $log?->id,
            'latency_ms' => $latency,
            'live_data_available' => true,
        ];
    }

    private function normalize(Item $item, string $term): array
    {
        $lowerTerm = mb_strtolower($term);
        $matchedKeyword = collect($this->keywords($term))->first(
            fn (string $keyword) => str_contains(mb_strtolower((string) $item->name), $keyword)
        );
        $reason = str_contains(mb_strtolower((string) $item->name), $lowerTerm)
            ? "Matched because the product name contains {$term}."
            : ($matchedKeyword
                ? "Matched because the product name contains {$matchedKeyword}."
            : (str_contains(mb_strtolower((string) $item->category?->name), $lowerTerm)
                ? "Matched because it belongs to the {$item->category?->name} category."
                : 'Matched available catalog details and popularity signals.'));

        return [
            'id' => $item->id,
            'name' => $item->name,
            'price' => $item->price,
            'store' => $item->store?->name,
            'category' => $item->category?->name,
            'image' => $item->image_full_url,
            'stock_status' => $item->stock > 0 ? 'available' : 'availability_check_required',
            'reason' => $reason,
        ];
    }

    private function keywords(string $term): array
    {
        $stopWords = ['about', 'affordable', 'available', 'best', 'buy', 'find', 'from', 'have', 'looking', 'need', 'please', 'project', 'show', 'some', 'that', 'this', 'with'];
        $words = preg_split('/[^\pL\pN]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, $stopWords, true)
        ))), 0, 8);
    }

    private function logSearch(array $actor, string $term, array $filters, array $itemIds, int $latency): ?AiSearchLog
    {
        try {
            return AiSearchLog::create([
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'query' => $this->auditService->redact($term),
                'filters' => $filters,
                'result_count' => count($itemIds),
                'result_item_ids' => $itemIds,
                'latency_ms' => $latency,
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI search logging failed.', ['error' => $exception->getMessage()]);

            return null;
        }
    }
}
