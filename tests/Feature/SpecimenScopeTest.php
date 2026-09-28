<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use App\Services\DocumentWorkflow;
use App\Services\SpecimenTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function scopeFixture(): array
{
    Storage::fake('local');
    $owner = User::factory()->create();
    $signer = User::factory()->create([
        'jabatan' => 'Analis Sistem Informasi',
        'unit_kerja' => 'Badan Perencanaan Pembangunan Daerah',
        'pangkat' => 'Penata',
        'golongan' => 'III/c',
    ]);
    foreach (['approver', 'signer'] as $type) {
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $signer->id, 'type' => $type]);
    }
    $document = Document::factory()->for($owner, 'owner')->create([
        'status' => DocumentStatus::Draft,
        'approver_id' => $signer->id,
        'signer_id' => $signer->id,
    ]);
    $upload = new UploadedFile(base_path('tests/Fixtures/scanned.pdf'), 'scan.pdf', 'application/pdf', null, true);
    app(DocumentWorkflow::class)->configure($document, $owner, [$signer->id], [$signer->id], $upload);

    return [$document->fresh(), $owner];
}

test('new signature steps default to all pages', function () {
    [$document] = scopeFixture();
    $step = $document->currentCycle()->signatures()->firstOrFail();

    expect($step->specimen_scope)->toBe('all_pages');
    expect(app(SpecimenTemplate::class)->options($document->currentCycle(), false)[0]['specimen_scope'])->toBe('all_pages');
});

test('owner can explicitly keep a specimen on the selected page', function () {
    [$document, $owner] = scopeFixture();
    $cycle = $document->currentCycle();
    $step = app(SpecimenTemplate::class)->options($cycle, false)[0];
    $layout = $step['layouts']['qr_2x2'];

    $this->actingAs($owner)->put(route('documents.pdf.place', $document), [
        'cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256,
        'confirmed' => 1,
        'positions' => [[
            'id' => $step['id'],
            'page' => 1,
            'x' => 20,
            'y' => 20,
            'width' => $layout['width'],
            'height' => $layout['height'],
            'specimen_format' => 'qr_2x2',
            'specimen_scope' => 'selected_page',
            'profile_fingerprint' => $step['profile_fingerprint'],
        ]],
    ])->assertSessionHasNoErrors();

    expect($cycle->signatures()->firstOrFail()->fresh()->specimen_scope)->toBe('selected_page');
});
