<?php

use App\Enums\DocumentStatus;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rejected document can be opened directly for correction by owner', function () {
    $owner = $this->signIn();

    $document = Document::factory()
        ->rejected(
            'Perbaiki isi dokumen.'
        )
        ->for($owner, 'owner')
        ->create();

    $this->get(
        route(
            'documents.edit',
            $document
        )
    )
        ->assertOk()
        ->assertSee(
            'Perbaiki Dokumen'
        )
        ->assertSee(
            'Perbaiki isi dokumen.'
        );
});

test('rejected document places one correction action beside document information', function () {
    $owner = $this->signIn();

    $document = Document::factory()
        ->rejected('Perbaiki isi dokumen.')
        ->for($owner, 'owner')
        ->create();

    $this->get(route('documents.show', $document))
        ->assertOk()
        ->assertSeeInOrder(['Informasi Dokumen', 'data-repair-action', 'Perbaiki Dokumen'])
        ->assertDontSee('Perbaiki Sekarang');
});

test('saving correction moves rejected document back to draft', function () {
    $owner = $this->signIn();

    $destination = User::factory()->create();
    $approver = User::factory()->create();
    $signer = User::factory()->create();

    foreach ([
        [
            WorkflowMasterType::Destination,
            $destination,
        ],
        [
            WorkflowMasterType::Approver,
            $approver,
        ],
        [
            WorkflowMasterType::Signer,
            $signer,
        ],
    ] as [$type, $target]) {
        WorkflowMasterEntry::query()
            ->create([
                'user_id' => $owner->id,
                'type' => $type,
                'target_user_id' => $target->id,
                'is_default' => true,
            ]);
    }

    $document = Document::factory()
        ->rejected(
            'Judul perlu diperbaiki.'
        )
        ->for($owner, 'owner')
        ->for(
            $destination,
            'destination'
        )
        ->for($approver, 'approver')
        ->for($signer, 'signer')
        ->create([
            'title' => 'Judul Lama',
        ]);

    $this->put(
        route(
            'documents.update',
            $document
        ),
        [
            'document_number' => $document->document_number,
            'title' => 'Judul Baru',
            'description' => $document->description,
            'destination_user_id' => $destination->id,
            'approver_id' => $approver->id,
            'signer_id' => $signer->id,
        ]
    )->assertRedirect();

    $document->refresh();

    expect($document->status)
        ->toBe(DocumentStatus::Draft);

    expect($document->title)
        ->toBe('Judul Baru');

    expect($document->revision_count)
        ->toBe(1);

    expect($document->rejection_reason)
        ->toBe(
            'Judul perlu diperbaiki.'
        );
});

test('non owner cannot edit rejected document', function () {
    $this->signIn();

    $owner = User::factory()->create();

    $document = Document::factory()
        ->rejected()
        ->for($owner, 'owner')
        ->create();

    $this->get(
        route(
            'documents.edit',
            $document
        )
    )->assertForbidden();
});
