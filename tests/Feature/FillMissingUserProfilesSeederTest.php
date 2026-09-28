<?php

use App\Models\User;
use Database\Seeders\FillMissingUserProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile seeder fills only blank fields for existing users including admin and is repeatable', function () {
    $user = User::factory()->create(['jabatan' => 'Jabatan Asli', 'unit_kerja' => null, 'pangkat' => ' ', 'golongan' => '']);
    $admin = User::factory()->admin()->create();
    $identity = $user->only(['name', 'email', 'password', 'role']);
    $this->seed(FillMissingUserProfilesSeeder::class);
    $user->refresh();
    expect($user->jabatan)->toBe('Jabatan Asli');
    expect($user->only(['name', 'email', 'password', 'role']))->toBe($identity);
    foreach ([$user, $admin->fresh()] as $account) {
        foreach (['jabatan', 'unit_kerja', 'pangkat', 'golongan'] as $field) {
            expect(trim($account->$field))->not->toBe('');
        }
    }
    $before = User::orderBy('id')->get()->toArray();
    $this->seed(FillMissingUserProfilesSeeder::class);
    expect(User::orderBy('id')->get()->toArray())->toBe($before);
    $this->assertDatabaseCount('users', 2);
});
