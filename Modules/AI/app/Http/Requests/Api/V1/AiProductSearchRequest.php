<?php

namespace Modules\AI\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AiProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
            'guest_id' => ['nullable', 'integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.category_id' => ['nullable', 'integer', 'min:1'],
            'filters.store_id' => ['nullable', 'integer', 'min:1'],
            'filters.min_price' => ['nullable', 'numeric', 'min:0'],
            'filters.max_price' => ['nullable', 'numeric', 'gte:filters.min_price'],
            'filters.location' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
