<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\AI\Entities\AiConversation;
use Modules\AI\Entities\AiMessage;
use Modules\AI\Entities\AiRecommendation;
use Modules\AI\Entities\AiSearchLog;
use Modules\AI\Entities\AiToolCall;
use Modules\AI\Entities\CustomerIntentLog;

class AiManagementController extends Controller
{
    public function index(Request $request): View
    {
        $definitions = $this->definitions();
        $type = $this->resolveType($request->string('type')->toString(), $definitions);
        $definition = $definitions[$type];
        $query = $definition['model']::query()->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($definition, $search) {
                foreach ($definition['searchable'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $records = $query->paginate(20)->withQueryString();
        $settings = $this->phaseOneSettings();

        return view('admin-views.ai-management.index', compact('definitions', 'type', 'definition', 'records', 'settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_provider' => ['required', Rule::in(['openai'])],
            'default_model' => ['required', 'string', 'max:100'],
            'request_timeout' => ['required', 'integer', 'between:5,120'],
            'max_tokens' => ['required', 'integer', 'between:100,4000'],
            'monthly_budget_limit' => ['nullable', 'numeric', 'min:0'],
            'per_user_daily_limit' => ['nullable', 'integer', 'min:0'],
            'enable_ai_chat' => ['nullable', 'boolean'],
            'enable_ai_search' => ['nullable', 'boolean'],
            'log_prompts' => ['nullable', 'boolean'],
            'redact_sensitive_data' => ['nullable', 'boolean'],
        ]);

        foreach (['enable_ai_chat', 'enable_ai_search', 'log_prompts', 'redact_sensitive_data'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'ai_phase_one_config'],
            ['value' => json_encode($data)]
        );

        Toastr::success('AI Phase 1 settings updated successfully.');

        return back();
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $definitions = $this->definitions();
        $type = $this->resolveType($type, $definitions);
        $definition = $definitions[$type];
        $data = $this->validatedData($request, $definition);

        $definition['model']::create($data);
        Toastr::success('AI record created successfully.');

        return redirect()->route('admin.ai-management.index', ['type' => $type]);
    }

    public function edit(string $type, int $id): View
    {
        $definitions = $this->definitions();
        $type = $this->resolveType($type, $definitions);
        $definition = $definitions[$type];
        $record = $definition['model']::query()->findOrFail($id);

        return view('admin-views.ai-management.edit', compact('definitions', 'type', 'definition', 'record'));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $definitions = $this->definitions();
        $type = $this->resolveType($type, $definitions);
        $definition = $definitions[$type];
        $record = $definition['model']::query()->findOrFail($id);
        $record->update($this->validatedData($request, $definition));

        Toastr::success('AI record updated successfully.');

        return redirect()->route('admin.ai-management.index', ['type' => $type]);
    }

    private function validatedData(Request $request, array $definition): array
    {
        $rules = collect($definition['fields'])->mapWithKeys(
            fn (array $field, string $name) => [$name => $field['rules']]
        )->all();
        $validator = Validator::make($request->all(), $rules);

        foreach ($definition['fields'] as $name => $field) {
            if (($field['type'] ?? null) !== 'json' || ! $request->filled($name)) {
                continue;
            }

            json_decode($request->input($name), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $validator->after(fn ($validator) => $validator->errors()->add($name, 'The field must contain valid JSON.'));
            }
        }

        $data = $validator->validate();

        foreach ($definition['fields'] as $name => $field) {
            if (($field['type'] ?? null) === 'json' && array_key_exists($name, $data)) {
                $data[$name] = filled($data[$name]) ? json_decode($data[$name], true) : null;
            }

            if (($field['type'] ?? null) === 'boolean') {
                $data[$name] = $request->boolean($name);
            }
        }

        return $data;
    }

    private function resolveType(string $type, array $definitions): string
    {
        return array_key_exists($type, $definitions) ? $type : 'conversations';
    }

    private function definitions(): array
    {
        $actorFields = [
            'actor_type' => $this->field('Actor type', 'text', ['nullable', 'string', 'max:100']),
            'actor_id' => $this->field('Actor ID', 'number', ['nullable', 'integer', 'min:1']),
        ];

        return [
            'conversations' => [
                'label' => 'Conversations',
                'model' => AiConversation::class,
                'searchable' => ['uuid', 'actor_type', 'guard', 'scope', 'channel', 'status'],
                'fields' => [
                    'uuid' => $this->field('UUID', 'text', ['required', 'uuid', Rule::unique('ai_conversations', 'uuid')->ignore(request()->route('id'))]),
                    ...$actorFields,
                    'guard' => $this->field('Guard', 'text', ['nullable', 'string', 'max:100']),
                    'scope' => $this->field('Scope', 'text', ['required', 'string', 'max:100'], 'shopping'),
                    'channel' => $this->field('Channel', 'text', ['nullable', 'string', 'max:100']),
                    'status' => $this->field('Status', 'select', ['required', Rule::in(['active', 'closed', 'archived'])], 'active', ['active', 'closed', 'archived']),
                    'metadata' => $this->field('Metadata', 'json', ['nullable', 'json']),
                ],
            ],
            'messages' => [
                'label' => 'Messages',
                'model' => AiMessage::class,
                'searchable' => ['role', 'content', 'redacted_content', 'model'],
                'fields' => [
                    'ai_conversation_id' => $this->field('Conversation ID', 'number', ['required', 'integer', 'exists:ai_conversations,id']),
                    'role' => $this->field('Role', 'select', ['required', Rule::in(['system', 'user', 'assistant', 'tool'])], 'user', ['system', 'user', 'assistant', 'tool']),
                    'content' => $this->field('Content', 'textarea', ['nullable', 'string']),
                    'redacted_content' => $this->field('Redacted content', 'textarea', ['nullable', 'string']),
                    'model' => $this->field('Model', 'text', ['nullable', 'string', 'max:255']),
                    'prompt_tokens' => $this->field('Prompt tokens', 'number', ['nullable', 'integer', 'min:0']),
                    'completion_tokens' => $this->field('Completion tokens', 'number', ['nullable', 'integer', 'min:0']),
                    'total_tokens' => $this->field('Total tokens', 'number', ['nullable', 'integer', 'min:0']),
                    'cost' => $this->field('Cost', 'number', ['nullable', 'numeric', 'min:0']),
                    'latency_ms' => $this->field('Latency (ms)', 'number', ['nullable', 'integer', 'min:0']),
                    'metadata' => $this->field('Metadata', 'json', ['nullable', 'json']),
                ],
            ],
            'tool-calls' => [
                'label' => 'Tool Calls',
                'model' => AiToolCall::class,
                'searchable' => ['tool_name', 'status', 'error_message'],
                'fields' => [
                    'ai_conversation_id' => $this->field('Conversation ID', 'number', ['nullable', 'integer', 'exists:ai_conversations,id']),
                    'ai_message_id' => $this->field('Message ID', 'number', ['nullable', 'integer', 'exists:ai_messages,id']),
                    'tool_name' => $this->field('Tool name', 'text', ['required', 'string', 'max:255']),
                    'input_hash' => $this->field('Input hash', 'text', ['nullable', 'string', 'max:255']),
                    'input_summary' => $this->field('Input summary', 'json', ['nullable', 'json']),
                    'output_summary' => $this->field('Output summary', 'json', ['nullable', 'json']),
                    'status' => $this->field('Status', 'select', ['required', Rule::in(['success', 'failed', 'pending', 'reviewed'])], 'success', ['success', 'failed', 'pending', 'reviewed']),
                    'error_message' => $this->field('Error message', 'textarea', ['nullable', 'string']),
                    'latency_ms' => $this->field('Latency (ms)', 'number', ['nullable', 'integer', 'min:0']),
                    'requires_human_review' => $this->field('Requires human review', 'boolean', ['nullable', 'boolean']),
                ],
            ],
            'search-logs' => [
                'label' => 'Search Logs',
                'model' => AiSearchLog::class,
                'searchable' => ['query', 'source', 'actor_type'],
                'fields' => [
                    ...$actorFields,
                    'query' => $this->field('Query', 'textarea', ['required', 'string']),
                    'filters' => $this->field('Filters', 'json', ['nullable', 'json']),
                    'result_count' => $this->field('Result count', 'number', ['required', 'integer', 'min:0'], 0),
                    'result_item_ids' => $this->field('Result item IDs', 'json', ['nullable', 'json']),
                    'clicked_item_id' => $this->field('Clicked item ID', 'number', ['nullable', 'integer', 'exists:items,id']),
                    'source' => $this->field('Source', 'text', ['required', 'string', 'max:100'], 'ai_product_search'),
                    'latency_ms' => $this->field('Latency (ms)', 'number', ['nullable', 'integer', 'min:0']),
                    'metadata' => $this->field('Metadata', 'json', ['nullable', 'json']),
                ],
            ],
            'recommendations' => [
                'label' => 'Recommendations',
                'model' => AiRecommendation::class,
                'searchable' => ['actor_type', 'recommendation_type', 'reason', 'outcome'],
                'fields' => [
                    ...$actorFields,
                    'item_id' => $this->field('Item ID', 'number', ['nullable', 'integer', 'exists:items,id']),
                    'store_id' => $this->field('Store ID', 'number', ['nullable', 'integer', 'exists:stores,id']),
                    'recommendation_type' => $this->field('Recommendation type', 'text', ['required', 'string', 'max:100'], 'assistant'),
                    'rank' => $this->field('Rank', 'number', ['nullable', 'integer', 'min:1']),
                    'reason' => $this->field('Reason', 'textarea', ['nullable', 'string']),
                    'context' => $this->field('Context', 'json', ['nullable', 'json']),
                    'outcome' => $this->field('Outcome', 'select', ['nullable', Rule::in(['impressed', 'clicked', 'added_to_cart', 'ordered', 'dismissed'])], null, ['', 'impressed', 'clicked', 'added_to_cart', 'ordered', 'dismissed']),
                ],
            ],
            'customer-intents' => [
                'label' => 'Customer Intents',
                'model' => CustomerIntentLog::class,
                'searchable' => ['actor_type', 'intent', 'location_text', 'urgency'],
                'fields' => [
                    ...$actorFields,
                    'ai_conversation_id' => $this->field('Conversation ID', 'number', ['nullable', 'integer', 'exists:ai_conversations,id']),
                    'intent' => $this->field('Intent', 'text', ['required', 'string', 'max:255']),
                    'category_id' => $this->field('Category ID', 'number', ['nullable', 'integer', 'exists:categories,id']),
                    'location_text' => $this->field('Location', 'text', ['nullable', 'string', 'max:255']),
                    'budget_min' => $this->field('Minimum budget', 'number', ['nullable', 'numeric', 'min:0']),
                    'budget_max' => $this->field('Maximum budget', 'number', ['nullable', 'numeric', 'gte:budget_min']),
                    'urgency' => $this->field('Urgency', 'text', ['nullable', 'string', 'max:100']),
                    'extracted_entities' => $this->field('Extracted entities', 'json', ['nullable', 'json']),
                    'confidence' => $this->field('Confidence', 'number', ['nullable', 'numeric', 'between:0,1']),
                ],
            ],
        ];
    }

    private function field(string $label, string $type, array $rules, mixed $default = null, array $options = []): array
    {
        return compact('label', 'type', 'rules', 'default', 'options');
    }

    private function phaseOneSettings(): array
    {
        $stored = BusinessSetting::query()->where('key', 'ai_phase_one_config')->value('value');
        $stored = $stored ? json_decode($stored, true) : [];

        return array_merge([
            'default_provider' => config('ai.default_provider', 'openai'),
            'default_model' => config('ai.default_model', 'gpt-4o-mini'),
            'request_timeout' => config('ai.request_timeout', 30),
            'max_tokens' => config('ai.max_tokens', 700),
            'monthly_budget_limit' => config('ai.monthly_budget_limit'),
            'per_user_daily_limit' => config('ai.per_user_daily_limit'),
            'enable_ai_chat' => config('ai.enable_ai_chat', true),
            'enable_ai_search' => config('ai.enable_ai_search', true),
            'log_prompts' => config('ai.log_prompts', true),
            'redact_sensitive_data' => config('ai.redact_sensitive_data', true),
        ], is_array($stored) ? $stored : []);
    }
}
