<?php

use App\Models\User;
use Database\Seeders\UserProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user profile seeder fills only empty fields and remains stable', function () {
    $user = User::factory()->create([
        'email' => 'sample01@demo.signwork.test',
        'jabatan' => 'Kepala Bidang',
        'unit_kerja' => null,
        'pangkat' => '',
        'golongan' => null,
    ]);

    $this->seed(UserProfileSeeder::class);
    $first = $user->fresh()->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']);

    expect($first['jabatan'])->toBe('Kepala Bidang');
    expect($first['unit_kerja'])->not->toBeEmpty();
    expect($first['pangkat'])->not->toBeEmpty();
    expect($first['golongan'])->not->toBeEmpty();

    $this->seed(UserProfileSeeder::class);

    expect($user->fresh()->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']))->toBe($first);
});

test('legacy uniform demo profile is replaced with a varied profile', function () {
    $user = User::factory()->create([
        'email' => 'sample02@demo.signwork.test',
        'jabatan' => 'Pelaksana',
        'unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Subang',
        'pangkat' => 'Penata Muda',
        'golongan' => 'III/a',
    ]);

    $this->seed(UserProfileSeeder::class);

    $user->refresh();

    expect([$user->jabatan, $user->unit_kerja, $user->pangkat, $user->golongan])
        ->not->toBe(['Pelaksana', 'Dinas Komunikasi dan Informatika Kabupaten Subang', 'Penata Muda', 'III/a']);
});

test('user profile seeder produces varied profile data across users', function () {
    foreach ([1, 2, 3, 4, 6] as $number) {
        User::factory()->create([
            'email' => sprintf('sample%02d@demo.signwork.test', $number),
            'jabatan' => null,
            'unit_kerja' => null,
            'pangkat' => null,
            'golongan' => null,
        ]);
    }

    $this->seed(UserProfileSeeder::class);

    $users = User::query()->where('email', 'like', 'sample%@demo.signwork.test')->get();

    expect($users->pluck('unit_kerja')->unique())->toHaveCount(5);
    expect($users->pluck('jabatan')->unique()->count())->toBeGreaterThan(1);
    expect($users->pluck('golongan')->unique()->count())->toBeGreaterThan(1);
});

test('admin profile seeding only fills unit kerja', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin.profile@example.test',
        'jabatan' => null,
        'unit_kerja' => null,
        'pangkat' => null,
        'golongan' => null,
    ]);

    $this->seed(UserProfileSeeder::class);

    $admin->refresh();

    expect($admin->unit_kerja)->not->toBeEmpty();
    expect($admin->jabatan)->toBeNull();
    expect($admin->pangkat)->toBeNull();
    expect($admin->golongan)->toBeNull();
});
