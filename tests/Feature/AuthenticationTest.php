<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page can be viewed', function () {
    $response = $this->get(
        route('login')
    );

    $response->assertOk();
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post(
        route('login.store'),
        [
            'email' => $user->email,
            'password' => 'password',
        ]
    );

    $response->assertRedirect(
        route('dashboard')
    );

    $this->assertAuthenticatedAs($user);
});

test('user cannot login with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(
        route('login.store'),
        [
            'email' => $user->email,
            'password' => 'password-salah',
        ]
    );

    $response->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('logout'));

    $response->assertRedirect(
        route('login')
    );

    $this->assertGuest();
});

test('guest is redirected to login when accessing documents', function () {
    $response = $this->get(
        route('documents.index')
    );

    $response->assertRedirect(
        route('login')
    );
});
