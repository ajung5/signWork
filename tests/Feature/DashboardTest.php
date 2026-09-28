<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard includes owned documents', function () {
    $user = $this->signIn();

    Document::factory()
        ->for($user, 'owner')
        ->create([
            'title' => 'Dokumen Milik Saya',
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Milik Saya'
        );
});

test('dashboard includes sent final document where user is destination', function () {
    $user = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for(
            $user,
            'destination'
        )
        ->create([
            'title' => 'Dokumen Tujuan Saya', 'status' => DocumentStatus::Signed, 'sent_at' => now(),
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Tujuan Saya'
        );
});

test('dashboard includes document where user is approver', function () {
    $user = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for($user, 'approver')
        ->create([
            'title' => 'Dokumen Approval Saya',
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Approval Saya'
        );
});

test('dashboard includes document where user is signer', function () {
    $user = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for($user, 'signer')
        ->create([
            'title' => 'Dokumen Sign Saya',
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Sign Saya'
        );
});

test('dashboard hides unrelated documents', function () {
    $user = $this->signIn();

    $owner = User::factory()->create();
    $other = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for(
            $other,
            'destination'
        )
        ->for($other, 'approver')
        ->for($other, 'signer')
        ->create([
            'title' => 'Tidak Boleh Terlihat',
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertDontSee(
            'Tidak Boleh Terlihat'
        );
});

test('admin dashboard can see all documents', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Semua User',
        ]);

    $this->get(
        route('dashboard')
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Semua User'
        );
});
