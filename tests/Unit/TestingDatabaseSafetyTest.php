<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TestingDatabaseSafetyTest extends TestCase
{
    public function test_phpunit_is_configured_for_a_dedicated_testing_database(): void
    {
        $environment = $this->environmentVariable('APP_ENV');
        $connection = $this->environmentVariable('DB_CONNECTION');
        $database = $this->environmentVariable('DB_DATABASE');

        $this->assertSame('testing', $environment);
        $this->assertContains($connection, ['mysql', 'mariadb']);
        $this->assertSame('food_distribution_system_testing', $database);
        $this->assertStringEndsWith('_testing', $database);
        $this->assertNotSame('food_distribution_system', $database);
    }

    private function environmentVariable(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
