<?php

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin can view user management', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    User::factory()->create([
        'name' => 'User Operasional',
        'email' => 'operasional@example.test',
    ]);

    $this->get(
        route('admin.users.index')
    )
        ->assertOk()
        ->assertSee('Manajemen User')
        ->assertSee('User Operasional')
        ->assertSee('Edit')
        ->assertSee('Hapus');
});

test('admin can create regular user', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    $response = $this->post(
        route('admin.users.store'),
        [
            'name' => 'User Baru',
            'email' => 'user.baru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]
    );

    $response
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('success');

    $user = User::query()
        ->where(
            'email',
            'user.baru@example.test'
        )
        ->firstOrFail();

    expect($user->role)
        ->toBe(UserRole::User);
});

test('admin can edit regular user without changing password', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create([
        'name' => 'Nama Lama',
        'email' => 'lama@example.test',
        'password' => 'password123',
    ]);

    $oldPassword = $user->password;

    $this->signIn($admin);

    $this->put(
        route(
            'admin.users.update',
            $user
        ),
        [
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
            'password' => '',
            'password_confirmation' => '',
        ]
    )
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)
        ->toBe('Nama Baru');

    expect($user->email)
        ->toBe('baru@example.test');

    expect($user->password)
        ->toBe($oldPassword);
});

test('updating NIK does not clear an existing user profile', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create([
        'name' => 'Nama Lama',
        'email' => 'profil@example.test',
        'jabatan' => 'Analis Sistem Informasi',
        'unit_kerja' => 'Diskominfo Kabupaten Subang',
        'pangkat' => 'Penata',
        'golongan' => 'III/c',
    ]);

    $profile = $user->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']);

    $this->signIn($admin);

    $this->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'nik' => '3200000000000003',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

    expect($user->fresh()->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']))->toBe($profile);
    expect($user->fresh()->nik)->toBe('3200000000000003');
});

test('admin can edit regular user and replace password', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create();

    $this->signIn($admin);

    $this->put(
        route(
            'admin.users.update',
            $user
        ),
        [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]
    )->assertRedirect(
        route('admin.users.index')
    );

    expect(
        Hash::check(
            'newpassword123',
            $user->fresh()->password
        )
    )->toBeTrue();
});

test('admin can delete unreferenced regular user', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create();

    $this->signIn($admin);

    $this->delete(
        route(
            'admin.users.destroy',
            $user
        )
    )
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('success');

    expect(
        User::query()
            ->whereKey($user->id)
            ->exists()
    )->toBeFalse();
});

test('admin can delete user whose only references are workflow master entries', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create();
    $other = User::factory()->create();

    WorkflowMasterEntry::query()
        ->create([
            'user_id' => $user->id,
            'type' => WorkflowMasterType::Approver,
            'target_user_id' => $other->id,
            'is_default' => true,
        ]);

    WorkflowMasterEntry::query()
        ->create([
            'user_id' => $other->id,
            'type' => WorkflowMasterType::Signer,
            'target_user_id' => $user->id,
            'is_default' => true,
        ]);

    $this->signIn($admin);

    $this->delete(
        route(
            'admin.users.destroy',
            $user
        )
    )
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('success');

    expect(
        User::query()
            ->whereKey($user->id)
            ->exists()
    )->toBeFalse();

    expect(
        WorkflowMasterEntry::query()
            ->where(
                'target_user_id',
                $user->id
            )
            ->exists()
    )->toBeFalse();
});

test('admin cannot delete user that is referenced by document history', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $user = User::factory()->create();

    Document::factory()
        ->for($user, 'owner')
        ->create();

    $this->signIn($admin);

    $this->delete(
        route(
            'admin.users.destroy',
            $user
        )
    )
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('error');

    expect(
        User::query()
            ->whereKey($user->id)
            ->exists()
    )->toBeTrue();
});

test('admin account cannot be edited or deleted from user management', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $otherAdmin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    $this->get(
        route(
            'admin.users.edit',
            $otherAdmin
        )
    )->assertForbidden();

    $this->delete(
        route(
            'admin.users.destroy',
            $otherAdmin
        )
    )
        ->assertRedirect(
            route('admin.users.index')
        )
        ->assertSessionHas('error');

    expect(
        User::query()
            ->whereKey($otherAdmin->id)
            ->exists()
    )->toBeTrue();
});

test('regular user cannot access admin user management actions', function () {
    $this->signIn();

    $target = User::factory()->create();

    $this->get(
        route('admin.users.index')
    )->assertForbidden();

    $this->get(
        route(
            'admin.users.edit',
            $target
        )
    )->assertForbidden();

    $this->put(
        route(
            'admin.users.update',
            $target
        ),
        [
            'name' => 'Forbidden',
            'email' => 'forbidden@example.test',
        ]
    )->assertForbidden();

    $this->delete(
        route(
            'admin.users.destroy',
            $target
        )
    )->assertForbidden();
});
