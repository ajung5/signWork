<?php

use App\Enums\DocumentStatus;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can assign signer from own master to legacy approved document', function () {
    $owner = $this->signIn();
    $signer = User::factory()->create();

    WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Signer,
            'target_user_id' => $signer->id,
            'is_default' => true,
        ]);

    $document = Document::factory()
        ->approved()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.assign-signer',
            $document
        ),
        [
            'signer_id' => $signer->id,
        ]
    )->assertRedirect();

    expect(
        $document->fresh()->status
    )->toBe(
        DocumentStatus::WaitingSignature
    );
});

test('owner cannot assign signer outside own master', function () {
    $owner = $this->signIn();
    $signer = User::factory()->create();

    $document = Document::factory()
        ->approved()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.assign-signer',
            $document
        ),
        [
            'signer_id' => $signer->id,
        ]
    )->assertSessionHasErrors(
        'signer_id'
    );
});

test('signed document is not shown in signature inbox', function () {
    $signer = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for($signer, 'signer')
        ->create([
            'title' => 'Sudah Ditandatangani',
            'status' => DocumentStatus::Signed,
            'signed_at' => now(),
        ]);

    Document::factory()
        ->waitingSignature()
        ->for($owner, 'owner')
        ->for($signer, 'signer')
        ->create([
            'title' => 'Menunggu Tanda Tangan',
        ]);

    $this->get(
        route('signatures.index')
    )
        ->assertOk()
        ->assertSee(
            'Menunggu Tanda Tangan'
        )
        ->assertDontSee(
            'Sudah Ditandatangani'
        );
});
