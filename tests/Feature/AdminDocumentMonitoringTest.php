<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can monitor all documents', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $firstOwner =
        User::factory()->create([
            'name' => 'Pemilik Pertama',
        ]);

    $secondOwner =
        User::factory()->create([
            'name' => 'Pemilik Kedua',
        ]);

    Document::factory()
        ->for($firstOwner, 'owner')
        ->create([
            'title' => 'Dokumen Pertama',
        ]);

    Document::factory()
        ->waitingApproval()
        ->for($secondOwner, 'owner')
        ->create([
            'title' => 'Dokumen Kedua',
        ]);

    $this->signIn($admin);

    $this->get(
        route('admin.documents.index')
    )
        ->assertOk()
        ->assertSee('Semua Dokumen')
        ->assertSee('Dokumen Pertama')
        ->assertSee('Dokumen Kedua')
        ->assertSee('Pemilik Pertama')
        ->assertSee('Pemilik Kedua');
});

test('admin document monitoring supports search', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $owner =
        User::factory()->create([
            'name' => 'Budi Monitoring',
        ]);

    Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Surat Khusus Monitoring',
        ]);

    Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Tidak Dicari',
        ]);

    $this->signIn($admin);

    $this->get(
        route(
            'admin.documents.index',
            [
                'q' => 'Surat Khusus',
            ]
        )
    )
        ->assertOk()
        ->assertSee(
            'Surat Khusus Monitoring'
        )
        ->assertDontSee(
            'Dokumen Tidak Dicari'
        );
});

test('admin document monitoring supports status filter', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Draft',
            'status' => DocumentStatus::Draft,
        ]);

    Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Approval',
        ]);

    $this->signIn($admin);

    $this->get(
        route(
            'admin.documents.index',
            [
                'status' => DocumentStatus::Draft->value,
            ]
        )
    )
        ->assertOk()
        ->assertSee('Dokumen Draft')
        ->assertDontSee(
            'Dokumen Approval'
        );
});

test('regular user cannot access admin document monitoring', function () {
    $this->signIn();

    $this->get(
        route('admin.documents.index')
    )->assertForbidden();
});
