<?php

namespace Tests\Unit;

use App\Traits\ActivationClass;
use PHPUnit\Framework\TestCase;

class ActivationClassTest extends TestCase
{
    use ActivationClass;

    private array $storedActivationConfig = [];

    public function test_existing_file_credentials_are_preserved(): void
    {
        $config = [
            'username' => 'existing-user',
            'purchase_key' => 'existing-key',
            'software_id' => 'software-id',
        ];

        $this->assertSame($config, $this->resolveActivationConfig('react_web', $config));
    }

    public function test_unknown_apps_are_not_modified(): void
    {
        $config = [
            'username' => '',
            'purchase_key' => '',
            'software_id' => 'software-id',
        ];

        $this->assertSame($config, $this->resolveActivationConfig('unknown_app', $config));
    }

    public function test_blank_file_credentials_are_recovered_from_enabled_stored_activation(): void
    {
        $this->storedActivationConfig = [
            'activation_status' => 1,
            'username' => 'stored-user',
            'purchase_key' => 'stored-key',
        ];

        $resolved = $this->resolveActivationConfig('react_web', [
            'active' => 0,
            'username' => '',
            'purchase_key' => '',
            'software_id' => 'software-id',
        ]);

        $this->assertSame(1, $resolved['active']);
        $this->assertSame('stored-user', $resolved['username']);
        $this->assertSame('stored-key', $resolved['purchase_key']);
    }

    public function test_disabled_stored_activation_does_not_reenable_addon(): void
    {
        $this->storedActivationConfig = [
            'activation_status' => 0,
            'username' => 'stored-user',
            'purchase_key' => 'stored-key',
        ];

        $config = [
            'active' => 0,
            'username' => '',
            'purchase_key' => '',
            'software_id' => 'software-id',
        ];

        $this->assertSame($config, $this->resolveActivationConfig('react_web', $config));
    }

    protected function getStoredActivationConfig(string $key): array
    {
        return $this->storedActivationConfig;
    }
}
