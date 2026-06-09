<?php

namespace Modules\AI\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiRecommendationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recommendation_id' => ['nullable', 'integer', 'min:1'],
            'item_id' => ['nullable', 'integer', 'min:1'],
            'store_id' => ['nullable', 'integer', 'min:1'],
            'guest_id' => ['nullable', 'integer', 'min:1'],
            'event' => ['required', Rule::in(['impressed', 'clicked', 'added_to_cart', 'ordered', 'dismissed'])],
            'recommendation_type' => ['nullable', 'string', 'max:100'],
            'context' => ['nullable', 'array'],
        ];
    }
}
