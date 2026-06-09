<?php

namespace Modules\AI\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }

    public function intentLogs(): HasMany
    {
        return $this->hasMany(CustomerIntentLog::class);
    }
}
