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

function specimenFixture(): array
{
    Storage::fake('local');
    $owner = User::factory()->create();
    $signer = User::factory()->create([
        'name' => 'Agung Nawawi, S.Kom', 'jabatan' => 'Pranata Komputer Ahli Pertama',
        'unit_kerja' => 'Dinas Komunikasi, Informatika, Persandian dan Statistik',
        'pangkat' => 'Penata Muda Tk. I', 'golongan' => 'III/b',
    ]);
    foreach (['approver', 'signer'] as $type) {
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $signer->id, 'type' => $type]);
    }
    $doc = Document::factory()->for($owner, 'owner')->create(['status' => DocumentStatus::Draft, 'approver_id' => $signer->id, 'signer_id' => $signer->id]);
    $upload = new UploadedFile(base_path('tests/Fixtures/scanned.pdf'), 'scan.pdf', 'application/pdf', null, true);
    app(DocumentWorkflow::class)->configure($doc, $owner, [$signer->id], [$signer->id], $upload);
    $cycle = $doc->fresh()->currentCycle();
    $step = app(SpecimenTemplate::class)->options($cycle)[0];
    $position = [...collect($step)->only(['id', 'profile_fingerprint'])->all(), 'specimen_format' => 'framed', 'page' => 1, 'x' => 30, 'y' => 150,
        'width' => $step['layouts']['framed']['width'], 'height' => $step['layouts']['framed']['height']];

    return [$doc->fresh(), $owner, $signer, $cycle, $position];
}

test('admin can store and update specimen profile fields', function () {
    $admin = User::factory()->admin()->create();
    $data = ['name' => 'Test, S.Kom', 'email' => 'specimen@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'nik' => '3200000000000002',
        'jabatan' => 'Analis', 'unit_kerja' => 'Diskominfo', 'pangkat' => 'Penata', 'golongan' => 'III/c'];
    $this->actingAs($admin)->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();
    $user = User::where('email', $data['email'])->firstOrFail();
    expect($user->only(['nik', 'jabatan', 'unit_kerja', 'pangkat', 'golongan']))->toBe(collect($data)->only(['nik', 'jabatan', 'unit_kerja', 'pangkat', 'golongan'])->all());
    $this->put(route('admin.users.update', $user), [...$data, 'jabatan' => 'Kepala Bidang'])->assertSessionHasNoErrors();
    expect($user->fresh()->jabatan)->toBe('Kepala Bidang');
    $this->actingAs(User::factory()->create())->put(route('admin.users.update', $user), $data)->assertForbidden();
    expect($user->fresh()->jabatan)->toBe('Kepala Bidang');
});

test('framed profile is frozen at confirmed placement through signing', function () {
    [$doc, $owner, $signer, $cycle, $position] = specimenFixture();
    $workflow = app(DocumentWorkflow::class);
    $this->actingAs($owner)->put(route('documents.pdf.place', $doc), ['cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => [$position]])->assertSessionHasNoErrors();
    $this->actingAs($signer)->put(route('profile.update'), ['name' => $signer->name, 'jabatan' => 'Jabatan Baru'])->assertSessionHasNoErrors();
    $workflow->submit($doc, $owner, $cycle->public_id);
    $workflow->decide($doc->fresh(), $signer, $cycle->public_id);
    $workflow->sign($doc->fresh(), $signer, $cycle->public_id);
    $step = $cycle->signatures()->first();
    expect($step->profile_snapshot['jabatan'])->toBe('Pranata Komputer Ahli Pertama');
    expect($step->specimen_format)->toBe('framed');
    expect($doc->fresh()->status)->toBe(DocumentStatus::Signed);
    expect(hash_file('sha256', Storage::disk('local')->path($step->output_path)))->toBe($cycle->fresh()->final_sha256);
});

test('stale profile and missing framed profile cannot be confirmed', function () {
    [$doc, $owner, $signer, $cycle, $position] = specimenFixture();
    $signer->update(['jabatan' => 'Jabatan Berubah']);
    $data = ['cycle_token' => $cycle->public_id, 'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => [$position]];
    $this->actingAs($owner)->put(route('documents.pdf.place', $doc), $data)->assertSessionHasErrors('positions');
    expect($cycle->fresh()->positions_confirmed_at)->toBeNull();
    $signer->update(['jabatan' => null]);
    $this->put(route('documents.pdf.place', $doc), $data)->assertSessionHasErrors('positions');
    expect($cycle->fresh()->positions_confirmed_at)->toBeNull();
    $data['positions'][0]['specimen_format'] = 'qr_2cm';
    $this->put(route('documents.pdf.place', $doc), $data)->assertSessionHasNoErrors();
    expect($cycle->signatures()->first()->width)->toBe(56.693);
});

test('framed geometry is validated at its full size against page bounds', function () {
    [$doc, $owner, $signer, $cycle, $position] = specimenFixture();
    $position['x'] = 400;
    $position['width'] = 1;
    $this->actingAs($owner)->put(route('documents.pdf.place', $doc), ['cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => [$position]])->assertSessionHasErrors('pdf');
    expect($cycle->fresh()->positions_confirmed_at)->toBeNull();
});

test('both QR sizes are enforced server side despite client dimensions', function (string $format, float $size) {
    [$doc, $owner, $signer, $cycle, $position] = specimenFixture();
    $position = [...$position, 'specimen_format' => $format, 'width' => 1, 'height' => 1];
    $this->actingAs($owner)->put(route('documents.pdf.place', $doc), ['cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => [$position]])->assertSessionHasNoErrors();
    $step = $cycle->signatures()->first();
    expect($step->width)->toBe($size);
    expect($step->height)->toBe($size);
    expect($step->specimen_format)->toBe($format);
})->with([['qr_2cm', 56.693], ['qr_3cm', 85.039]]);

test('new documents default to all-page specimen placement', function () {
    [$doc, $owner, $signer, $cycle] = specimenFixture();

    expect($cycle->signatures()->first()->specimen_scope)->toBe('all_pages');
});

test('placeholder documents default to framed format and preserve every placeholder page', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $approver = User::factory()->create();
    $signers = User::factory()->count(2)->create([
        'jabatan' => 'Pranata Komputer',
        'unit_kerja' => 'Diskominfo',
        'pangkat' => 'Penata',
        'golongan' => 'III/c',
    ]);
    foreach ($signers as $signer) {
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $signer->id, 'type' => 'signer']);
    }
    WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $approver->id, 'type' => 'approver']);
    $doc = Document::factory()->for($owner, 'owner')->create([
        'status' => DocumentStatus::Draft,
        'signer_id' => $signers[0]->id,
    ]);

    app(DocumentWorkflow::class)->configure(
        $doc,
        $owner,
        [$approver->id],
        $signers->pluck('id')->all(),
        new UploadedFile(base_path('tests/Fixtures/two-signers.pdf'), 'two-signers.pdf', 'application/pdf', null, true),
    );

    $steps = $doc->fresh()->currentCycle()->signatures()->orderBy('sequence')->get();
    expect($steps[0]->specimen_format)->toBe('framed')
        ->and($steps[0]->placement_source)->toBe('placeholder')
        ->and($steps[0]->specimen_scope)->toBe('selected_pages')
        ->and($steps[0]->specimen_pages)->toBe([1])
        ->and($steps[0]->specimen_positions['1'])->toHaveKeys(['x', 'y', 'width', 'height'])
        ->and($steps[1]->specimen_format)->toBe('framed')
        ->and($steps[1]->placement_source)->toBe('placeholder')
        ->and($steps[1]->specimen_pages)->toBe([1])
        ->and($steps[1]->specimen_positions['1'])->toHaveKeys(['x', 'y', 'width', 'height']);

    $options = app(SpecimenTemplate::class)->options($doc->fresh()->currentCycle(), false);
    expect($options[0]['placeholder_rects']['1'])->toHaveKeys(['x', 'y', 'width', 'height'])
        ->and($options[1]['placeholder_rects']['1'])->toHaveKeys(['x', 'y', 'width', 'height']);
});

test('placeholder mode rejects a missing signer token instead of falling back to manual placement', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $approver = User::factory()->create();
    $signers = User::factory()->count(2)->create();
    foreach ($signers as $signer) {
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $signer->id, 'type' => 'signer']);
    }
    WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $approver->id, 'type' => 'approver']);
    $doc = Document::factory()->for($owner, 'owner')->create(['status' => DocumentStatus::Draft]);

    expect(fn (): mixed => app(DocumentWorkflow::class)->configure(
        $doc,
        $owner,
        [$approver->id],
        $signers->pluck('id')->all(),
        new UploadedFile(base_path('tests/Fixtures/duplicate.pdf'), 'placeholder.pdf', 'application/pdf', null, true),
    ))->toThrow(\Illuminate\Validation\ValidationException::class, 'Placeholder signer belum lengkap');

    expect($doc->fresh()->currentCycle())->toBeNull();
});

test('existing cycles are rescanned before placeholder configuration is rebuilt', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $approver = User::factory()->create();
    $signers = User::factory()->count(2)->create([
        'jabatan' => 'Pranata Komputer',
        'unit_kerja' => 'Diskominfo',
        'pangkat' => 'Penata',
        'golongan' => 'III/c',
    ]);
    foreach ($signers as $signer) {
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $signer->id, 'type' => 'signer']);
    }
    WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $approver->id, 'type' => 'approver']);
    $doc = Document::factory()->for($owner, 'owner')->create(['status' => DocumentStatus::Draft]);
    $workflow = app(DocumentWorkflow::class);
    $upload = new UploadedFile(base_path('tests/Fixtures/two-signers.pdf'), 'placeholder.pdf', 'application/pdf', null, true);
    $workflow->configure($doc, $owner, [$approver->id], $signers->pluck('id')->all(), $upload);
    $cycle = $doc->fresh()->currentCycle();
    $cycle->update(['pdf_metadata' => ['pages' => [], 'placeholders' => []]]);
    $workflow->configure($doc->fresh(), $owner, [$approver->id], $signers->pluck('id')->all(), null);

    expect($doc->fresh()->currentCycle()->signatures()->pluck('placement_source')->all())->toBe(['placeholder', 'placeholder']);
});

test('all-page specimen scope is persisted and validated', function () {
    [$doc, $owner, $signer, $cycle, $position] = specimenFixture();
    $position['specimen_scope'] = 'all_pages';

    $this->actingAs($owner)->put(route('documents.pdf.place', $doc), ['cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => [$position]])->assertSessionHasNoErrors();

    expect($cycle->signatures()->first()->fresh()->specimen_scope)->toBe('all_pages');
});
