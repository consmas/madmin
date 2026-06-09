<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersV39ApiTest extends TestCase
{
    public function test_helpers_exposes_v39_view_api(): void
    {
        $this->assertTrue(method_exists(Helpers::class, 'highlight'));
        $this->assertTrue(method_exists(Helpers::class, 'getCountries'));
        $this->assertTrue(method_exists(Helpers::class, 'getLanguages'));
        $this->assertTrue(method_exists(Helpers::class, 'get_data_settings'));
        $this->assertTrue(method_exists(Helpers::class, 'vehicle_data_formatting'));
        $this->assertTrue(method_exists(Helpers::class, 'product_video_data_formatting'));
        $this->assertTrue(method_exists(Helpers::class, 'get_verified_seller_status'));
    }
}
