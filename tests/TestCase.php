<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        $this->ensureSafeTestingDatabase();

        parent::setUp();
    }

    private function ensureSafeTestingDatabase(): void
    {
        $environment = $this->environmentVariable('APP_ENV');
        $connection = $this->environmentVariable('DB_CONNECTION');
        $database = $this->environmentVariable('DB_DATABASE');

        if ($environment !== 'testing') {
            throw new LogicException(
                "Tests were blocked because APP_ENV is not 'testing'."
            );
        }

        if (! in_array($connection, ['mysql', 'mariadb'], true)) {
            throw new LogicException(
                "Tests were blocked because DB_CONNECTION must be mysql or mariadb."
            );
        }

        if ($database === '' || ! str_ends_with($database, '_testing')) {
            throw new LogicException(
                "Tests were blocked because DB_DATABASE must end with '_testing'."
            );
        }

        if ($database === 'food_distribution_system') {
            throw new LogicException(
                'Tests were blocked because they are targeting the development database.'
            );
        }
    }

    private function environmentVariable(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
