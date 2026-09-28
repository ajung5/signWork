<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException(
                'Test dihentikan: APP_ENV bukan testing.'
            );
        }

        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException(
                'Test dihentikan: database test harus menggunakan SQLite.'
            );
        }

        if (
            config('database.connections.sqlite.database')
            !== ':memory:'
        ) {
            throw new RuntimeException(
                'Test dihentikan: SQLite test harus menggunakan :memory:.'
            );
        }
    }
}
