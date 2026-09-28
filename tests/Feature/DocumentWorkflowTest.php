<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner submit goes directly to waiting approval when approver is selected', function () {
    $owner = $this->signIn();
    $approver = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for($approver, 'approver')
        ->create();

    $response = $this->post(
        route(
            'documents.submit',
            $document
        )
    );

    $response->assertRedirect(
        route(
            'documents.show',
            $document
        )
    );

    $document->refresh();

    expect($document->status)
        ->toBe(
            DocumentStatus::WaitingApproval
        );

    expect($document->submitted_at)
        ->not->toBeNull();

    expect(
        $document->approver_assigned_at
    )->not->toBeNull();
});

test('owner can select themselves as approver before submit', function () {
    $owner = $this->signIn();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for($owner, 'approver')
        ->create();

    $this->post(
        route(
            'documents.submit',
            $document
        )
    )->assertRedirect();

    $document->refresh();

    expect($document->approver_id)
        ->toBe($owner->id);

    expect($document->status)
        ->toBe(
            DocumentStatus::WaitingApproval
        );
});

test('legacy draft without approver becomes submitted', function () {
    $owner = $this->signIn();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create([
            'approver_id' => null,
        ]);

    $this->post(
        route(
            'documents.submit',
            $document
        )
    )->assertRedirect();

    $document->refresh();

    expect($document->status)
        ->toBe(
            DocumentStatus::Submitted
        );
});

test('another user cannot submit owners document', function () {
    $this->signIn();

    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.submit',
            $document
        )
    )->assertForbidden();
});

test('submitted document cannot be submitted twice', function () {
    $owner = $this->signIn();

    $document = Document::factory()
        ->submitted()
        ->for($owner, 'owner')
        ->create();

    $this->post(
        route(
            'documents.submit',
            $document
        )
    )->assertStatus(409);
});
