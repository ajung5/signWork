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

test('user dashboard does not show the create document button', function () {
    $user = $this->signIn();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Buat Dokumen');
});

test('dashboard highlights the next action for a PDF draft', function () {
    $user = $this->signIn();

    Document::factory()
        ->for($user, 'owner')
        ->create([
            'title' => 'Draft Perlu Diselesaikan',
            'requires_pdf_workflow' => true,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tugas Saya Berikutnya')
        ->assertSee('Draft Perlu Diselesaikan')
        ->assertSee('Atur dokumen');
});

test('dashboard highlights the assigned verification task', function () {
    $user = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for($user, 'approver')
        ->create([
            'title' => 'Menunggu Verifikasi Saya',
            'status' => DocumentStatus::WaitingApproval,
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tugas Saya Berikutnya')
        ->assertSee('Menunggu Verifikasi Saya')
        ->assertSee('Verifikasi dokumen');
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
