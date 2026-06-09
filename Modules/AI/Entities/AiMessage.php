<?php

namespace Modules\AI\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array', 'cost' => 'decimal:8'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }
}
