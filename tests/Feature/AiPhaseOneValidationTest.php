<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiPhaseOneValidationTest extends TestCase
{
    public function test_ai_chat_requires_a_message(): void
    {
        $this->postJson('/api/v1/ai/chat', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_ai_product_search_requires_a_query(): void
    {
        $this->postJson('/api/v1/ai/search/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('query');
    }

    public function test_recommendation_event_rejects_unknown_event(): void
    {
        $this->postJson('/api/v1/ai/recommendations/events', ['event' => 'mutate_order'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event');
    }
}
