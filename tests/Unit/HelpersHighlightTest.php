<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersHighlightTest extends TestCase
{
    public function test_it_highlights_dollar_delimited_text(): void
    {
        $this->assertSame(
            'Deliver <span class="hl">faster</span> today',
            Helpers::highlight('Deliver $faster$ today')
        );
    }

    public function test_it_escapes_html_before_highlighting(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt; <span class="hl">safe</span>',
            Helpers::highlight('<script>alert(1)</script> $safe$')
        );
    }

    public function test_it_returns_an_empty_string_for_empty_input(): void
    {
        $this->assertSame('', Helpers::highlight(null));
    }
}
