<?php

use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedDocumentMasterFor(
    User $owner,
    User $destination,
    User $approver,
    User $signer
): void {
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
}

test('document can be created from users workflow master', function () {
    $owner = $this->signIn();
    $destination = User::factory()->create();
    $approver = User::factory()->create();
    $signer = User::factory()->create();

    seedDocumentMasterFor(
        $owner,
        $destination,
        $approver,
        $signer
    );

    $this->post(
        route('documents.store'),
        [
            'document_number' => '001/SIGNWORK/2026',
            'title' => 'Dokumen Pengujian',
            'description' => 'Dokumen untuk pengujian.',
            'destination_user_id' => $destination->id,
            'approver_id' => $approver->id,
            'signer_id' => $signer->id,
        ]
    )->assertRedirect();

    $document =
        Document::query()
            ->where(
                'document_number',
                '001/SIGNWORK/2026'
            )
            ->firstOrFail();

    expect(
        $document->destination_user_id
    )->toBe($destination->id);

    expect($document->approver_id)
        ->toBe($approver->id);

    expect($document->signer_id)
        ->toBe($signer->id);
});

test('document cannot select user outside owners workflow master', function () {
    $owner = $this->signIn();

    $destination = User::factory()->create();
    $approver = User::factory()->create();
    $signer = User::factory()->create();
    $outside = User::factory()->create();

    seedDocumentMasterFor(
        $owner,
        $destination,
        $approver,
        $signer
    );

    $this->post(
        route('documents.store'),
        [
            'title' => 'Invalid Master Selection',
            'destination_user_id' => $outside->id,
            'approver_id' => $approver->id,
            'signer_id' => $signer->id,
        ]
    )->assertSessionHasErrors(
        'destination_user_id'
    );
});

test('document create page selects default master entries', function () {
    $owner = $this->signIn();

    $destination = User::factory()->create();
    $approver = User::factory()->create();
    $signer = User::factory()->create();

    seedDocumentMasterFor(
        $owner,
        $destination,
        $approver,
        $signer
    );

    $this->get(
        route('documents.create')
    )
        ->assertOk()
        ->assertSee($destination->name)
        ->assertSee($approver->name)
        ->assertSee($signer->name);
});
