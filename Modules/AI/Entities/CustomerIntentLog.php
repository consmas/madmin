<?php

namespace Modules\AI\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIntentLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'extracted_entities' => 'array',
        'budget_min' => 'decimal:4',
        'budget_max' => 'decimal:4',
        'confidence' => 'decimal:4',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
