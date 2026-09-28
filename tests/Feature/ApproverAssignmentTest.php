<?php

use App\Enums\DocumentStatus;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can assign approver from own master to legacy submitted document', function () {
    $owner = $this->signIn();
    $approver = User::factory()->create();

    WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Approver,
            'target_user_id' => $approver->id,
            'is_default' => true,
        ]);

    $document = Document::factory()
        ->submitted()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.assign-approver',
            $document
        ),
        [
            'approver_id' => $approver->id,
        ]
    )->assertRedirect();

    expect(
        $document->fresh()->status
    )->toBe(
        DocumentStatus::WaitingApproval
    );
});

test('owner cannot assign approver outside own master', function () {
    $owner = $this->signIn();
    $approver = User::factory()->create();

    $document = Document::factory()
        ->submitted()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.assign-approver',
            $document
        ),
        [
            'approver_id' => $approver->id,
        ]
    )->assertSessionHasErrors(
        'approver_id'
    );
});

test('approval inbox only contains waiting approval documents assigned to user', function () {
    $approver = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->waitingApproval()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create([
            'title' => 'Masih Menunggu',
        ]);

    Document::factory()
        ->approved()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create([
            'title' => 'Sudah Selesai Approval',
        ]);

    $this->get(
        route('approvals.index')
    )
        ->assertOk()
        ->assertSee('Masih Menunggu')
        ->assertDontSee(
            'Sudah Selesai Approval'
        );
});
