<?php

namespace Tests\Unit;

use Modules\AI\app\Services\AiAuditService;
use Modules\AI\app\Services\AiOpenAIService;
use PHPUnit\Framework\TestCase;

class AiPhaseOneServicesTest extends TestCase
{
    public function test_audit_service_redacts_common_sensitive_values(): void
    {
        $service = new AiAuditService;

        $redacted = $service->redact('Email buyer@example.com or call +233 24 123 4567. api_key=secret-value');

        $this->assertStringNotContainsString('buyer@example.com', $redacted);
        $this->assertStringNotContainsString('+233 24 123 4567', $redacted);
        $this->assertStringNotContainsString('secret-value', $redacted);
    }

    public function test_intent_classifier_identifies_product_and_delivery_requests(): void
    {
        $service = new AiOpenAIService(new AiAuditService);

        $this->assertSame('product_search', $service->classifyIntent('I need cement for a project')['intent']);
        $this->assertSame('delivery_question', $service->classifyIntent('Can you deliver this tomorrow?')['intent']);
    }
}
