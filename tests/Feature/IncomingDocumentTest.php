<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('incoming inbox shows signed and explicitly sent document addressed to current user', function () {
    $destination = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for(
            $destination,
            'destination'
        )
        ->create([
            'title' => 'Dokumen Masuk Saya',
            'status' => DocumentStatus::Signed,
            'submitted_at' => now(), 'sent_at' => now(),
        ]);

    $this->get(
        route(
            'incoming-documents.index'
        )
    )
        ->assertOk()
        ->assertSee(
            'Dokumen Masuk Saya'
        );
});

test('incoming inbox tracks unread and read state for the destination user', function () {
    $destination = $this->signIn();
    $owner = User::factory()->create();

    $document = Document::factory()
        ->for($owner, 'owner')
        ->for($destination, 'destination')
        ->create([
            'title' => 'Dokumen Perlu Dibaca',
            'status' => DocumentStatus::Signed,
            'submitted_at' => now(),
            'sent_at' => now(),
            'read_at' => null,
        ]);

    $this->get(route('incoming-documents.index'))
        ->assertOk()
        ->assertSee('Belum dibaca')
        ->assertSee('data-read-status="unread"', false)
        ->assertDontSee('Ditandatangani');

    $this->get(route('documents.show', $document))->assertOk();

    expect($document->fresh()->read_at)->not->toBeNull();

    $this->get(route('incoming-documents.index'))
        ->assertSee('Sudah dibaca')
        ->assertSee('data-read-status="read"', false)
        ->assertDontSee('Belum dibaca');
});

test('incoming inbox does not show another users destination document', function () {
    $this->signIn();

    $owner = User::factory()->create();
    $destination =
        User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for(
            $destination,
            'destination'
        )
        ->create([
            'title' => 'Dokumen Tujuan Orang Lain',
            'status' => DocumentStatus::Signed,
            'submitted_at' => now(), 'sent_at' => now(),
        ]);

    $this->get(
        route(
            'incoming-documents.index'
        )
    )
        ->assertOk()
        ->assertDontSee(
            'Dokumen Tujuan Orang Lain'
        );
});

test('draft is not shown in incoming inbox', function () {
    $destination = $this->signIn();
    $owner = User::factory()->create();

    Document::factory()
        ->for($owner, 'owner')
        ->for(
            $destination,
            'destination'
        )
        ->create([
            'title' => 'Draft Rahasia',
        ]);

    $this->get(
        route(
            'incoming-documents.index'
        )
    )->assertDontSee('Draft Rahasia');
});
