<?php

namespace Modules\AI\app\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\AI\Entities\AiConversation;
use Modules\AI\Entities\AiMessage;

class AiConversationService
{
    public function __construct(private readonly AiAuditService $auditService) {}

    public function createOrResolveConversation(Request $request): AiConversation
    {
        $actor = $this->getActorFromRequest($request);
        $uuid = $request->input('conversation_uuid');

        if ($uuid && $actor['id'] !== null) {
            $conversation = AiConversation::query()
                ->where('uuid', $uuid)
                ->where('actor_type', $actor['type'])
                ->where('actor_id', $actor['id'])
                ->where('status', 'active')
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::create([
            'uuid' => (string) Str::uuid(),
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'guard' => $actor['guard'],
            'scope' => 'shopping',
            'channel' => $request->input('channel'),
            'metadata' => ['context' => $request->input('context', [])],
        ]);
    }

    public function storeUserMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        return $conversation->messages()->create([
            'role' => 'user',
            'content' => config('ai.log_prompts', true) ? $content : null,
            'redacted_content' => $this->auditService->redact($content),
            'metadata' => $metadata,
        ]);
    }

    public function storeAssistantMessage(AiConversation $conversation, string $content, array $metadata = []): AiMessage
    {
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => config('ai.log_prompts', true) ? $content : null,
            'redacted_content' => $this->auditService->redact($content),
            'model' => $metadata['model'] ?? null,
            'prompt_tokens' => $metadata['prompt_tokens'] ?? null,
            'completion_tokens' => $metadata['completion_tokens'] ?? null,
            'total_tokens' => $metadata['total_tokens'] ?? null,
            'latency_ms' => $metadata['latency_ms'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    public function attachTokenUsage(AiMessage $message, array $usage): AiMessage
    {
        $message->update([
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ]);

        return $message;
    }

    public function closeConversation(AiConversation $conversation): void
    {
        $conversation->update(['status' => 'closed']);
    }

    public function getActorFromRequest(Request $request): array
    {
        $customer = $request->user() ?: auth('api')->user();
        if ($customer) {
            return ['type' => 'customer', 'id' => $customer->getKey(), 'guard' => 'api'];
        }

        if ($request->get('vendor_employee')) {
            return ['type' => 'vendor_employee', 'id' => $request->get('vendor_employee')->getKey(), 'guard' => 'vendor_employee'];
        }

        if ($request->get('vendor')) {
            return ['type' => 'vendor', 'id' => $request->get('vendor')->getKey(), 'guard' => 'vendor'];
        }

        foreach (['admin', 'vendor', 'vendor_employee'] as $guard) {
            if (auth($guard)->check()) {
                return ['type' => $guard, 'id' => auth($guard)->id(), 'guard' => $guard];
            }
        }

        if ($request->input('guest_id')) {
            return ['type' => 'guest', 'id' => (int) $request->input('guest_id'), 'guard' => 'guest'];
        }

        return ['type' => 'anonymous', 'id' => null, 'guard' => null];
    }
}
