<?php

namespace Modules\AI\Entities;

use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'filters' => 'array',
        'result_item_ids' => 'array',
        'metadata' => 'array',
    ];

    public function clickedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'clicked_item_id');
    }
}
