<?php

namespace Modules\AI\app\Services;

use Illuminate\Support\Facades\Log;
use Modules\AI\Entities\AiToolCall;
use Throwable;

class AiAuditService
{
    public function redact(?string $value): ?string
    {
        if ($value === null || (app()->bound('config') && ! config('ai.redact_sensitive_data', true))) {
            return $value;
        }

        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $value);
        $value = preg_replace('/(?<!\d)(?:\d[ -]*?){13,19}(?!\d)/', '[REDACTED_PAYMENT_NUMBER]', $value);
        $value = preg_replace('/(?<!\d)(?:\+?\d[\d\s().\-]{7,}\d)(?!\d)/', '[REDACTED_PHONE]', $value);
        $value = preg_replace('/\b\d{1,5}\s+[\pL0-9 .-]+\s(?:street|st|road|rd|avenue|ave|close|crescent|lane|ln)\b/iu', '[REDACTED_ADDRESS]', $value);
        $value = preg_replace('/\b(?:sk-|api[_-]?key|password|secret|token)\s*[:=]\s*\S+/i', '[REDACTED_SECRET]', $value);

        return $value;
    }

    public function logToolCall(array $attributes): ?AiToolCall
    {
        try {
            $attributes['input_summary'] = $this->redactArray($attributes['input_summary'] ?? null);
            $attributes['output_summary'] = $this->redactArray($attributes['output_summary'] ?? null);
            $attributes['error_message'] = $this->redact($attributes['error_message'] ?? null);

            return AiToolCall::create($attributes);
        } catch (Throwable $exception) {
            Log::warning('AI audit logging failed.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    private function redactArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        array_walk_recursive($data, function (&$value): void {
            if (is_string($value)) {
                $value = $this->redact($value);
            }
        });

        return $data;
    }
}
