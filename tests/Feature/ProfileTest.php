<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot read or change profiles', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->put(route('profile.update'), ['name' => 'Unauthorized'])->assertRedirect(route('login'));
});

test('authenticated users can view their own profile', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role, 'jabatan' => 'Analis']);
    $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Profil Saya')->assertSee($user->email)->assertSee('Analis');
})->with([UserRole::User, UserRole::Admin]);

test('authenticated users can open the read-only profile view and edit link', function () {
    $user = User::factory()->create(['jabatan' => 'Analis']);

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Profil Saya')
        ->assertSee('Edit Profil')
        ->assertSee('Analis');
});

test('profile updates only the authenticated identity fields and displays modal feedback', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $before = $other->fresh()->getAttributes();
    $password = $user->password;
    $data = ['name' => 'Akbar, S.Kom', 'jabatan' => 'Analis', 'unit_kerja' => 'Diskominfo', 'pangkat' => 'Penata', 'golongan' => 'III/c'];
    $this->actingAs($user)->put(route('profile.update'), [...$data, 'user_id' => $other->id, 'id' => $other->id,
        'role' => 'admin', 'email' => 'changed@example.test', 'password' => 'new-secret-123'])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->assertDatabaseHas('users', ['id' => $user->id, ...$data, 'email' => $user->email, 'password' => $password]);
    expect($user->fresh()->role)->toBe(UserRole::User);
    expect($other->fresh()->getAttributes())->toBe($before);
    $this->get(route('profile.edit'))->assertSee('data-feedback-modal', false)->assertSee('Profil berhasil diperbarui');
});

test('invalid profile data is rejected without saving', function (string $key, mixed $value, string $message) {
    $user = User::factory()->create();
    $before = $user->fresh()->getAttributes();
    $this->actingAs($user)->put(route('profile.update'), ['name' => 'Valid', $key => $value])->assertSessionHasErrors([$key => $message]);
    expect($user->fresh()->getAttributes())->toBe($before);
})->with([
    ['name', '', 'nama lengkap wajib diisi.'],
    ['jabatan', ['invalid'], 'jabatan harus berupa teks.'],
    ['unit_kerja', str_repeat('a', 256), 'unit kerja maksimal 255 karakter.'],
    ['pangkat', str_repeat('a', 101), 'pangkat maksimal 100 karakter.'],
    ['golongan', str_repeat('a', 31), 'golongan maksimal 30 karakter.'],
]);

test('profile view escapes identity values', function () {
    $payload = '<script>alert(1)</script>';
    $user = User::factory()->create(array_fill_keys(['name', 'jabatan', 'unit_kerja', 'pangkat', 'golongan'], $payload));
    $this->actingAs($user)->get(route('profile.edit'))->assertSee($payload)->assertDontSee($payload, false);
});
