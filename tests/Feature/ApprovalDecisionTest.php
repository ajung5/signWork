<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('assigned approver can approve once and action becomes locked', function () {
    $approver = $this->signIn();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create();

    $this->post(
        route(
            'documents.approve',
            $document
        )
    )->assertRedirect();

    expect(
        $document->fresh()->status
    )->toBe(DocumentStatus::Approved);

    $this->post(
        route(
            'documents.approve',
            $document
        )
    )
        ->assertRedirect(
            route(
                'documents.show',
                $document
            )
        )
        ->assertSessionHas('info');
});

test('assigned approver can reject once and action becomes locked', function () {
    $approver = $this->signIn();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create();

    $this->post(
        route(
            'documents.reject',
            $document
        ),
        [
            'rejection_reason' => 'Perbaiki substansi dokumen.',
        ]
    )->assertRedirect();

    expect(
        $document->fresh()->status
    )->toBe(DocumentStatus::Rejected);

    $this->post(
        route(
            'documents.reject',
            $document
        ),
        [
            'rejection_reason' => 'Percobaan kedua.',
        ]
    )
        ->assertRedirect(
            route(
                'documents.show',
                $document
            )
        )
        ->assertSessionHas('info');
});

test('approval moves directly to waiting signature when signer already exists', function () {
    $approver = $this->signIn();
    $owner = User::factory()->create();
    $signer = User::factory()->create();

    $document = Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->for($signer, 'signer')
        ->create();

    $this->post(
        route(
            'documents.approve',
            $document
        )
    )->assertRedirect();

    expect(
        $document->fresh()->status
    )->toBe(
        DocumentStatus::WaitingSignature
    );
});

test('unassigned user cannot make approval decision', function () {
    $this->signIn();

    $owner = User::factory()->create();
    $approver = User::factory()->create();

    $document = Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create();

    $this->post(
        route(
            'documents.approve',
            $document
        )
    )->assertForbidden();
});
