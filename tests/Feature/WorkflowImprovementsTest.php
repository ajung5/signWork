<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use App\Services\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function improvementDocument(): array
{
    Storage::set('local', Storage::fake('improvements-'.Str::uuid()));
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $approver = User::factory()->create();
    $signers = User::factory()->count(2)->create();
    foreach (['approver' => [$approver], 'signer' => $signers] as $type => $users) {
        foreach ($users as $user) {
            WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $user->id, 'type' => $type]);
        }
    }
    $document = Document::factory()->create(['owner_id' => $owner->id, 'destination_user_id' => $recipient->id, 'approver_id' => $approver->id, 'signer_id' => $signers[0]->id, 'requires_pdf_workflow' => true]);
    $workflow = app(DocumentWorkflow::class);
    $workflow->configure($document, $owner, [$approver->id], $signers->modelKeys(), new UploadedFile(base_path('tests/Fixtures/two-signers.pdf'), 'source.pdf', null, null, true));
    $document->refresh();
    $cycle = $document->currentCycle();
    $workflow->place($document, $owner, $cycle->public_id, $cycle->original_sha256, $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all());
    $workflow->submit($document, $owner, $cycle->public_id);
    $workflow->decide($document, $approver, $cycle->public_id);

    return [$document->fresh(), $owner, $recipient, $signers];
}

test('passphrase rejection preserves signing state and logs no credentials', function () {
    [$document, $owner, $recipient, $signers] = improvementDocument();
    $cycle = $document->currentCycle();
    $before = $cycle->current_sha256;
    $payload = ['cycle_token' => $cycle->public_id, 'mock_acknowledged' => 1, 'passphrase' => 'secret-that-must-not-leak'];

    $this->actingAs($signers[0])->post(route('documents.sign', $document), $payload)->assertSessionHasErrors('passphrase')->assertSessionMissing('_old_input.passphrase');

    expect($cycle->fresh()->current_sha256)->toBe($before);
    expect($cycle->signatures()->first()->status)->toBe('pending');
    $log = ActivityLog::where('event', 'documents.sign')->latest('id')->firstOrFail();
    expect($log->outcome)->toBe('error');
    expect($log->toJson())->not->toContain('secret-that-must-not-leak');

    $payload['passphrase'] = 'MOCK-SIGNWORK-2026';
    $this->post(route('documents.sign', $document), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($cycle->signatures()->first()->status)->toBe('signed');
    expect(ActivityLog::where('event', 'documents.sign')->latest('id')->firstOrFail()->outcome)->toBe('success');
});

test('recipient receives final document only after owner or last signer sends it', function (string $sender) {
    [$document, $owner, $recipient, $signers] = improvementDocument();
    $cycle = $document->currentCycle();
    $payload = ['cycle_token' => $cycle->public_id];
    $this->actingAs($recipient)->get(route('documents.show', $document))->assertForbidden();
    $this->actingAs($owner)->post(route('documents.send', $document), $payload)->assertForbidden();
    $workflow = app(DocumentWorkflow::class);
    foreach ($signers as $signer) {
        $workflow->sign($document, $signer, $cycle->public_id, 'MOCK-SIGNWORK-2026');
    }
    $this->actingAs($recipient)->get(route('incoming-documents.index'))->assertDontSee($document->title);
    $this->actingAs($signers[0])->post(route('documents.send', $document), $payload)->assertForbidden();

    $actor = $sender === 'owner' ? $owner : $signers[1];
    $this->actingAs($actor)->post(route('documents.send', $document), $payload)->assertSessionHasNoErrors();

    expect($document->fresh()->sent_by)->toBe($actor->id);
    expect($document->fresh()->sent_at)->not->toBeNull();
    $this->post(route('documents.send', $document), $payload)->assertForbidden();
    $this->actingAs($recipient)->get(route('incoming-documents.index'))->assertSee($document->title);
    $this->get(route('documents.show', $document))->assertOk();
})->with(['owner', 'last_signer']);

test('preview is authorized and returns PDF inline while download remains explicit', function () {
    [$document, $owner] = improvementDocument();
    $this->actingAs($owner)->get(route('documents.pdf.viewer', $document))
        ->assertSee('aria-label="Kembali ke detail dokumen"', false)
        ->assertSee('Unduh PDF ini');
    $this->get(route('documents.pdf.download', [$document, 'inline' => 1]))->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline; filename="SignWork-preview.pdf"');
    $this->get(route('documents.pdf.download', $document))->assertDownload();
    $this->actingAs(User::factory()->create())->get(route('documents.pdf.viewer', $document))->assertForbidden();
});

test('activity menu is admin only and supports outcome filter', function () {
    $user = User::factory()->create();
    ActivityLog::create(['event' => 'documents.send', 'outcome' => 'success', 'message' => 'Delivery success']);
    ActivityLog::create(['event' => 'documents.sign', 'outcome' => 'error', 'message' => 'Signing rejected']);
    $this->actingAs($user)->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.activity.index', ['outcome' => 'error']))->assertSee('Signing rejected')->assertDontSee('Delivery success');
});

test('Word uploads are converted to a private PDF with original preserved', function (string $extension) {
    Storage::set('local', Storage::fake('word-'.Str::uuid()));
    $owner = User::factory()->create();
    $approver = User::factory()->create();
    $signer = User::factory()->create();
    $document = Document::factory()->create(['owner_id' => $owner->id, 'approver_id' => $approver->id, 'signer_id' => $signer->id]);

    $this->actingAs($owner)->post(route('documents.pdf.store', $document), [
        'pdf' => new UploadedFile(base_path('tests/Fixtures/word-source.'.$extension), 'letter.'.$extension, null, null, true),
        'approvers' => [$approver->id], 'signers' => [$signer->id],
    ])->assertSessionHasNoErrors();

    $cycle = $document->fresh()->currentCycle();
    expect($cycle->source_name)->toBe('letter.'.$extension);
    expect($cycle->source_path)->not->toBe($cycle->original_path);
    Storage::disk('local')->assertExists([$cycle->source_path, $cycle->original_path]);
    expect(substr(Storage::disk('local')->get($cycle->original_path), 0, 5))->toBe('%PDF-');
})->with(['docx', 'doc']);

test('oversized upload is rejected and failed Word conversion leaves no cycle', function () {
    Storage::set('local', Storage::fake('upload-'.Str::uuid()));
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $document = Document::factory()->create(['owner_id' => $owner->id, 'approver_id' => $actor->id, 'signer_id' => $actor->id]);
    $payload = ['approvers' => [$actor->id], 'signers' => [$actor->id]];
    $this->actingAs($owner)->post(route('documents.pdf.store', $document), [...$payload, 'pdf' => UploadedFile::fake()->create('oversize.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('pdf');
    config(['signwork.libreoffice' => '/nonexistent/soffice']);
    $this->post(route('documents.pdf.store', $document), [...$payload, 'pdf' => new UploadedFile(base_path('tests/Fixtures/word-source.docx'), 'source.docx', null, null, true)])->assertSessionHasErrors('pdf');
    expect($document->cycles()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('passphrase attempts have an independent five per minute limit', function () {
    [$document, $owner, $recipient, $signers] = improvementDocument();
    $payload = ['cycle_token' => $document->currentCycle()->public_id, 'mock_acknowledged' => 1, 'passphrase' => 'wrong'];
    $this->actingAs($signers[0]);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('documents.sign', $document), $payload)->assertSessionHasErrors('passphrase');
    }
    $this->post(route('documents.sign', $document), $payload)->assertStatus(429);
    expect($document->currentCycle()->signatures()->first()->status)->toBe('pending');
});
