<?php

namespace Modules\AI\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_uuid' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'channel' => ['nullable', 'string', 'max:50'],
            'guest_id' => ['nullable', 'integer', 'min:1'],
            'context' => ['nullable', 'array'],
            'context.location' => ['nullable', 'string', 'max:255'],
            'context.budget' => ['nullable', 'numeric', 'min:0'],
            'context.filters' => ['nullable', 'array'],
        ];
    }
}
