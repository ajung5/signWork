<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use App\Models\User;

abstract class TestCase extends BaseTestCase {
    public function signIn(?User $user = null): User {
        $user ??= User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    protected function setUp(): void {
        $this->forceIsolatedTestEnvironment();

        parent::setUp();

        if (!app()->environment('testing')) {
            throw new RuntimeException('Test dihentikan: APP_ENV bukan testing.');
        }

        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Test dihentikan: database test harus menggunakan SQLite.');
        }

        if (config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Test dihentikan: SQLite test harus menggunakan :memory:.');
        }
    }

    /**
     * Set the test environment before Laravel boots the application.
     *
     * This protects `php artisan test` when the local .env uses MySQL or when
     * phpunit.xml is not applied early enough by the Artisan test runner.
     */
    private function forceIsolatedTestEnvironment(): void {
        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_FOREIGN_KEYS' => 'true',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'FILESYSTEM_DISK' => 'local'
        ];

        foreach ($environment as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
