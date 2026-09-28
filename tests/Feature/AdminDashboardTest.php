<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin dashboard shows monitoring chart and ten latest documents section', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Monitoring Admin',
            'status' => DocumentStatus::Draft,
        ]);

    Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Menunggu Persetujuan',
        ]);

    $response = $this->get(
        route('dashboard')
    );

    $response
        ->assertOk()
        ->assertViewIs('dashboard.admin')
        ->assertSee('Dashboard Admin')
        ->assertSee('Distribusi Workflow')
        ->assertSee('10 Dokumen Terbaru')
        ->assertSee('Total Dokumen')
        ->assertSee('Draft')
        ->assertSee('Menunggu Persetujuan')
        ->assertSee('Dokumen Monitoring Admin');
});

test('admin sidebar exposes dashboard all documents and user management only', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $this->signIn($admin);

    $response = $this->get(
        route('dashboard')
    );

    $response
        ->assertOk()
        ->assertSee('Semua Dokumen')
        ->assertSee('Manajemen User')
        ->assertDontSee(
            'href="'.route('documents.index').'"',
            false
        )
        ->assertDontSee(
            'href="'.route('incoming-documents.index').'"',
            false
        )
        ->assertDontSee(
            'href="'.route('approvals.index').'"',
            false
        )
        ->assertDontSee(
            'href="'.route('signatures.index').'"',
            false
        )
        ->assertDontSee(
            'href="'.route('workflow-settings.edit').'"',
            false
        );
});

test('admin still cannot create or modify workflow documents', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create();

    $this->signIn($admin);

    $this->get(
        route('documents.create')
    )->assertForbidden();

    $this->post(
        route('documents.store'),
        [
            'title' => 'Tidak Boleh Dibuat Admin',
        ]
    )->assertForbidden();

    $this->get(
        route(
            'documents.edit',
            $document
        )
    )->assertForbidden();

    $this->delete(
        route(
            'documents.destroy',
            $document
        )
    )->assertForbidden();
});

test('admin can review document detail read only', function () {
    $admin = User::factory()
        ->admin()
        ->create();

    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create([
            'title' => 'Dokumen Read Only',
        ]);

    $this->signIn($admin);

    $this->get(
        route(
            'documents.show',
            $document
        )
    )
        ->assertOk()
        ->assertSee('Dokumen Read Only');
});
