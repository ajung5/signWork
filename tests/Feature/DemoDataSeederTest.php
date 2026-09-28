<?php

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo seeder creates fifteen normal users', function () {
    $this->seed(
        DemoDataSeeder::class
    );

    $users = User::query()
        ->where(
            'email',
            'like',
            '%@demo.signwork.test'
        )
        ->get();

    expect($users)
        ->toHaveCount(15);

    expect(
        $users
            ->where(
                'role',
                UserRole::User
            )
            ->count()
    )->toBe(15);
});

test('demo users receive multiple workflow master entries', function () {
    $this->seed(
        DemoDataSeeder::class
    );

    $demoUserIds = User::query()
        ->where(
            'email',
            'like',
            '%@demo.signwork.test'
        )
        ->pluck('id');

    foreach (
        WorkflowMasterType::cases() as $type
    ) {
        expect(
            WorkflowMasterEntry::query()
                ->whereIn(
                    'user_id',
                    $demoUserIds
                )
                ->where(
                    'type',
                    $type->value
                )
                ->count()
        )->toBeGreaterThanOrEqual(15);
    }
});

test('each demo user has one default for every master type', function () {
    $this->seed(
        DemoDataSeeder::class
    );

    $users = User::query()
        ->where(
            'email',
            'like',
            '%@demo.signwork.test'
        )
        ->get();

    foreach ($users as $user) {
        foreach (
            WorkflowMasterType::cases() as $type
        ) {
            expect(
                WorkflowMasterEntry::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'type',
                        $type->value
                    )
                    ->where(
                        'is_default',
                        true
                    )
                    ->count()
            )->toBe(1);
        }
    }
});

test('each demo user owns five to ten documents', function () {
    $this->seed(
        DemoDataSeeder::class
    );

    $users = User::query()
        ->where(
            'email',
            'like',
            '%@demo.signwork.test'
        )
        ->withCount('documents')
        ->get();

    foreach ($users as $user) {
        expect(
            $user->documents_count
        )
            ->toBeGreaterThanOrEqual(5)
            ->toBeLessThanOrEqual(10);
    }

    expect(
        $users->sum(
            'documents_count'
        )
    )->toBe(108);
});

test('demo seeder is idempotent', function () {
    $this->seed(
        DemoDataSeeder::class
    );

    $this->seed(
        DemoDataSeeder::class
    );

    expect(
        User::query()
            ->where(
                'email',
                'like',
                '%@demo.signwork.test'
            )
            ->count()
    )->toBe(15);

    expect(
        Document::query()
            ->where(
                'document_number',
                'like',
                '%/SF-DEMO/%'
            )
            ->count()
    )->toBe(108);
});
