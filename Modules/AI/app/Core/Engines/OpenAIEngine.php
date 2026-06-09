<?php

namespace Modules\AI\app\Core\Engines;

use Modules\AI\app\Core\Contracts\AIEngineInterface;
use Modules\AI\app\Services\AiOpenAIService;
use RuntimeException;

class OpenAIEngine implements AIEngineInterface
{
    public function boot(): void
    {
        // TODO: Implement boot() method.
    }

    public function core($prompt, $imageUrl = null): string
    {
        $content = [['type' => 'text', 'text' => $prompt]];

        if (! empty($imageUrl)) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageUrl],
            ];
        }

        $response = app(AiOpenAIService::class)->chat([
            [
                'role' => 'user',
                'content' => $content,
            ],
        ], [
            // Keep the existing autofill model configurable without changing its API.
            'model' => config('ai.default_model', 'gpt-4o-mini'),
            'temperature' => 0.3,
        ]);

        if (! $response['success']) {
            throw new RuntimeException('AI provider unavailable.');
        }

        return $response['content'];
    }
}
