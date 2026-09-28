<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user role is cast to user enum', function () {
    $user = User::factory()->create();

    expect($user->role)
        ->toBe(UserRole::User);
});

test('admin factory creates admin user', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    expect($admin->role)
        ->toBe(UserRole::Admin);

    expect($admin->isAdmin())
        ->toBeTrue();
});

test('unrelated user cannot view document', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create();

    $this->actingAs($user)
        ->get(
            route(
                'documents.show',
                $document
            )
        )
        ->assertForbidden();
});

test('destination user can view sent final document', function () {
    $destination = User::factory()->create();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for(
            $destination,
            'destination'
        )
        ->create(['status' => DocumentStatus::Signed, 'sent_at' => now()]);

    $this->actingAs($destination)
        ->get(
            route(
                'documents.show',
                $document
            )
        )
        ->assertOk();
});

test('assigned approver can view document without special role', function () {
    $approver = User::factory()->create();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create();

    $this->actingAs($approver)
        ->get(
            route(
                'documents.show',
                $document
            )
        )
        ->assertOk();
});

test('assigned signer can view document without special role', function () {
    $signer = User::factory()->create();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for($signer, 'signer')
        ->create();

    $this->actingAs($signer)
        ->get(
            route(
                'documents.show',
                $document
            )
        )
        ->assertOk();
});

test('admin can view another users document', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create();

    $this->actingAs($admin)
        ->get(
            route(
                'documents.show',
                $document
            )
        )
        ->assertOk();
});
