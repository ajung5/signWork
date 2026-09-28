<?php

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Database\Seeders\FiveUserDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('five user document seeder creates five users with fifteen documents each', function () {
    $this->seed(FiveUserDocumentSeeder::class);

    $users = User::query()
        ->where('email', 'like', 'sample%@demo.signwork.test')
        ->withCount('documents')
        ->get();

    expect($users)->toHaveCount(5);
    expect($users->where('role', UserRole::User)->count())->toBe(5);

    foreach ($users as $user) {
        expect($user->documents_count)->toBe(15);
        expect($user->jabatan)->not->toBeEmpty();
        expect($user->unit_kerja)->not->toBeEmpty();
        expect($user->pangkat)->not->toBeEmpty();
        expect($user->golongan)->not->toBeEmpty();
    }

    expect($users->sum('documents_count'))->toBe(75);
    expect($users->pluck('unit_kerja')->unique())->toHaveCount(5);
    expect(Document::query()->whereIn('owner_id', $users->pluck('id'))->count())->toBe(75);
});

test('five seeded users receive two workflow targets for every master type', function () {
    $this->seed(FiveUserDocumentSeeder::class);

    $userIds = User::query()
        ->where('email', 'like', 'sample%@demo.signwork.test')
        ->pluck('id');

    foreach (WorkflowMasterType::cases() as $type) {
        expect(
            WorkflowMasterEntry::query()
                ->whereIn('user_id', $userIds)
                ->where('type', $type->value)
                ->count()
        )->toBe(10);
    }
});

test('five user document seeder is idempotent', function () {
    $this->seed(FiveUserDocumentSeeder::class);
    $this->seed(FiveUserDocumentSeeder::class);

    $users = User::query()->where('email', 'like', 'sample%@demo.signwork.test')->get();

    expect($users)->toHaveCount(5);
    expect(Document::query()->whereIn('owner_id', $users->pluck('id'))->count())->toBe(75);
});
