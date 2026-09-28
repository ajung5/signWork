<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use App\Services\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function twoPagePdfUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'two-page-source.pdf',
        base64_decode(
            'JVBERi0xLjcKJcK1wrYKCjEgMCBvYmoKPDwvVHlwZS9DYXRhbG9nL1BhZ2VzIDIgMCBSPj4KZW5kb2JqCgoyIDAgb2JqCjw8L1R5cGUvUGFnZXMvQ291bnQgMi9LaWRzWzQgMCBSIDkgMCBSXT4+CmVuZG9iagoKMyAwIG9iago8PC9Gb250PDwvaGVsdiA1IDAgUj4+Pj4KZW5kb2JqCgo0IDAgb2JqCjw8L1R5cGUvUGFnZS9NZWRpYUJveFswIDAgNTk1IDg0Ml0vUm90YXRlIDAvUmVzb3VyY2VzIDMgMCBSL1BhcmVudCAyIDAgUi9Db250ZW50c1s2IDAgUiA3IDAgUl0+PgplbmRvYmoKCjUgMCBvYmoKPDwvVHlwZS9Gb250L1N1YnR5cGUvVHlwZTEvQmFzZUZvbnQvSGVsdmV0aWNhL0VuY29kaW5nL1dpbkFuc2lFbmNvZGluZz4+CmVuZG9iagoKNiAwIG9iago8PC9MZW5ndGggNzg+PgpzdHJlYW0KCnEKQlQKMSAwIDAgMSA0MCA3NDIgVG0KL2hlbHYgMTIgVGYgWzwyNDdiNzQ3NDY1M2E3MzY5Njc2ZTY1NzIzYTMxN2Q+XVRKCkVUClEKCmVuZHN0cmVhbQplbmRvYmoKCjcgMCBvYmoKPDwvTGVuZ3RoIDc5Pj4Kc3RyZWFtCgpxCkJUCjEgMCAwIDEgMzAwIDc0MiBUbQovaGVsdiAxMiBUZiBbPDI0N2I3NDc0NjUzYTczNjk2NzZlNjU3MjNhMzI3ZD5dVEoKRVQKUQoKZW5kc3RyZWFtCmVuZG9iagoKOCAwIG9iago8PD4+CmVuZG9iagoKOSAwIG9iago8PC9UeXBlL1BhZ2UvTWVkaWFCb3hbMCAwIDU5NSA4NDJdL1JvdGF0ZSAwL1Jlc291cmNlcyA4IDAgUi9QYXJlbnQgMiAwIFI+PgplbmRvYmoKCnhyZWYKMCAxMAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAwMTYgMDAwMDAgbiAKMDAwMDAwMDA2MiAwMDAwMCBuIAowMDAwMDAwMTIwIDAwMDAwIG4gCjAwMDAwMDAxNjEgMDAwMDAgbiAKMDAwMDAwMDI3NCAwMDAwMCBuIAowMDAwMDAwMzYzIDAwMDAwIG4gCjAwMDAwMDA0OTAgMDAwMDAgbiAKMDAwMDAwMDYxOCAwMDAwMCBuIAowMDAwMDAwNjM5IDAwMDAwIG4gCgp0cmFpbGVyCjw8L1NpemUgMTAvUm9vdCAxIDAgUi9JRFs8NkEwQzY2QzJCREMyOENDM0JFQzI4NUMyOUEzNkMyODA+PDdGQ0QyNDQxMEVDNTQ1NjlCNjQ5MzY4MDlGQjUyNzhBPl0+PgpzdGFydHhyZWYKNzMwCiUlRU9GCg==',
            true
        )
    );
}

/** @return array{Document, User, array<User>, array<User>} */
function pdfWorkflowFixture(?UploadedFile $upload = null): array
{
    Storage::fake('local');
    $owner = User::factory()->create();
    $approvers = User::factory()->count(2)->create()->all();
    $signers = User::factory()->count(2)->create()->all();
    foreach (['approver' => $approvers, 'signer' => $signers] as $type => $users) {
        foreach ($users as $user) {
            WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $user->id, 'type' => $type]);
        }
    }
    $document = Document::factory()->for($owner, 'owner')->create(['status' => DocumentStatus::Draft, 'approver_id' => $approvers[0]->id, 'signer_id' => $signers[0]->id]);
    $file = $upload ?? new UploadedFile(base_path('tests/Fixtures/two-signers.pdf'), 'two-signers.pdf', 'application/pdf', null, true);
    app(DocumentWorkflow::class)->configure($document, $owner, array_column($approvers, 'id'), array_column($signers, 'id'), $file);
    $document->refresh();
    $cycle = $document->currentCycle();
    app(DocumentWorkflow::class)->place($document, $owner, $cycle->public_id, $cycle->original_sha256, $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all());

    return [$document, $owner, $approvers, $signers];
}

function submitPdfWorkflow(Document $document, User $owner): void
{
    app(DocumentWorkflow::class)->submit($document, $owner, $document->currentCycle()->public_id);
    $document->refresh();
}

function approvePdfWorkflow(Document $document, array $approvers): void
{
    foreach ($approvers as $approver) {
        app(DocumentWorkflow::class)->decide($document, $approver, $document->currentCycle()->public_id);
    }
    $document->refresh();
}

test('full workflow runs two approvals and two mock signatures and verifies the exact final bytes', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    $token = $document->currentCycle()->public_id;
    $this->actingAs($owner)->get(route('documents.pdf.edit', $document))
        ->assertOk()
        ->assertSee('aria-label="Kembali ke detail dokumen"', false)
        ->assertSee('Periksa posisi');
    $this->post(route('documents.submit', $document), ['cycle_token' => $token])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('documents.index'));
    expect($document->fresh()->currentCycle()->prepared_path)->not->toBeNull();
    $this->actingAs($owner)->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('data-workflow-timeline', false)
        ->assertSee('Draft')
        ->assertSee('Verifikasi')
        ->assertSee('Tanda tangan')
        ->assertSee('Dikirim')
        ->assertSee('Preview dokumen dan posisi spesimen')
        ->assertSee('Preview PDF tahap berjalan')
        ->assertDontSee('Buka verifikasi QR')
        ->assertDontSee('Preview dokumen tahap ini')
        ->assertDontSee('Preview dokumen sumber');
    $this->actingAs($approvers[0])->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('id="workflow-action"', false)
        ->assertSee('Keputusan Verifikasi');
    $this->actingAs($approvers[1])->post(route('documents.approve', $document), ['cycle_token' => $token])->assertForbidden();
    foreach ($approvers as $approver) {
        $this->actingAs($approver)->post(route('documents.approve', $document), ['cycle_token' => $token])->assertSessionHasNoErrors();
    }
    $this->actingAs($signers[1])->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertForbidden();
    $this->actingAs($signers[0])->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasNoErrors();
    $cycle = $document->currentCycle();
    expect($cycle->final_sha256)->toBeNull();
    expect($cycle->signatures()->first()->status)->toBe('signed');
    $this->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertForbidden();
    $this->actingAs($signers[1])->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasNoErrors();
    $cycle->refresh();
    expect($document->fresh()->status)->toBe(DocumentStatus::Signed);
    $this->actingAs($owner)->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee('Buka verifikasi QR');
    expect(hash_file('sha256', Storage::disk('local')->path($cycle->current_path)))->toBe($cycle->final_sha256);
    $steps = $cycle->signatures()->get();
    expect($steps[1]->input_sha256)->toBe($steps[0]->output_sha256);
    expect($steps[0]->input_sha256)->toBe($cycle->prepared_sha256);
    expect($cycle->original_sha256)->not->toBe($cycle->final_sha256);
    $this->get(route('verification.show', $token))->assertOk()->assertSee('BUKAN TTE SAH')->assertDontSee($owner->email);
    $this->post(route('verification.compare', $token), ['sha256' => $cycle->final_sha256])->assertSee('Hash cocok');
    $this->post(route('verification.compare', $token), ['sha256' => str_repeat('0', 64)])->assertSee('Hash tidak cocok');
    $this->get(route('documents.pdf.download', [$document, 'version' => 'final']))->assertDownload('SignWork-MOCK-final.pdf');
});

test('rejection preserves evidence and revision requires approval from step one again', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    $first = $document->currentCycle();
    app(DocumentWorkflow::class)->decide($document, $approvers[0], $first->public_id);
    app(DocumentWorkflow::class)->decide($document, $approvers[1], $first->public_id, 'Isi surat perlu diperbaiki.');
    app(DocumentWorkflow::class)->updateMetadata($document, $owner, ['title' => 'Revisi surat']);
    $document->refresh();
    $second = $document->currentCycle();
    expect($second->public_id)->not->toBe($first->public_id);
    expect($second->approvals()->pluck('status')->all())->toBe(['pending', 'pending']);
    expect($first->fresh()->approvals()->pluck('status')->all())->toBe(['approved', 'rejected']);
    expect($first->fresh()->signatures()->pluck('status')->all())->toBe(['not_processed', 'not_processed']);
    expect($document->status)->toBe(DocumentStatus::Draft);
    $this->actingAs($owner)->post(route('documents.submit', $document), ['cycle_token' => $first->public_id])->assertStatus(409);
    $this->delete(route('documents.destroy', $document))->assertForbidden();
});

test('source tampering stops approval without recording a decision', function () {
    [$document, $owner, $approvers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    $cycle = $document->currentCycle();
    Storage::disk('local')->append($cycle->original_path, 'tampered');
    $this->actingAs($approvers[0])->post(route('documents.approve', $document), ['cycle_token' => $cycle->public_id])->assertSessionHasErrors('pdf');
    expect($cycle->approvals()->first()->status)->toBe('pending');
});

test('an unrelated user cannot view download preview or configure a PDF', function () {
    [$document] = pdfWorkflowFixture();
    $this->actingAs(User::factory()->create());
    foreach (['documents.show', 'documents.pdf.edit', 'documents.pdf.review', 'documents.pdf.review-file', 'documents.pdf.download'] as $route) {
        $this->get(route($route, $document))->assertForbidden();
    }
    $this->get(route('documents.pdf.preview', [$document, 'page' => 1]))->assertForbidden();
    $this->post(route('documents.pdf.store', $document), [])->assertForbidden();
});

test('future participant can read the document but cannot approve out of turn', function () {
    [$document, $owner, $approvers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    $this->actingAs($approvers[1])->get(route('documents.show', $document))->assertOk()->assertDontSee('Setujui Dokumen');
    $this->post(route('documents.approve', $document), ['cycle_token' => $document->currentCycle()->public_id])->assertForbidden();
});

test('draft verification and unfinished final download are unavailable', function () {
    [$document, $owner] = pdfWorkflowFixture();
    $token = $document->currentCycle()->public_id;
    $this->get(route('verification.show', $token))->assertNotFound();
    submitPdfWorkflow($document, $owner);
    $this->get(route('verification.show', $token))->assertSee('Dokumen masih diproses')->assertDontSee('Bandingkan file Anda');
    $this->actingAs($owner)->get(route('documents.pdf.download', [$document, 'version' => 'final']))->assertStatus(409);
});

test('signature failure rolls back its step and can be retried', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    approvePdfWorkflow($document, $approvers);
    $interpreter = config('signwork.python');
    config(['signwork.python' => '/nonexistent/signwork-python']);
    $token = $document->currentCycle()->public_id;
    $this->actingAs($signers[0])->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasErrors('pdf');
    expect($document->fresh()->status)->toBe(DocumentStatus::WaitingSignature);
    expect($document->currentCycle()->prepared_path)->not->toBeNull();
    expect($document->currentCycle()->signatures()->first()->status)->toBe('pending');
    config(['signwork.python' => $interpreter]);
    $this->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasNoErrors();
    expect($document->currentCycle()->signatures()->first()->status)->toBe('signed');
});

test('real provider configuration cannot silently execute mock signing', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    approvePdfWorkflow($document, $approvers);
    config(['signwork.provider' => 'bsre']);
    $this->actingAs($signers[0])->post(route('documents.sign', $document), ['cycle_token' => $document->currentCycle()->public_id, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasErrors('provider');
    expect($document->currentCycle()->final_sha256)->toBeNull();
});

test('overlapping positions are rejected and workflow configuration locks after submit', function () {
    [$document, $owner] = pdfWorkflowFixture();
    $cycle = $document->currentCycle();
    $positions = $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all();
    $positions[1]['x'] = $positions[0]['x'];
    $this->actingAs($owner)->put(route('documents.pdf.place', $document), ['cycle_token' => $cycle->public_id, 'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => $positions])->assertSessionHasErrors('pdf');
    submitPdfWorkflow($document, $owner);
    $this->post(route('documents.pdf.store', $document), [])->assertForbidden();
});

test('selected pages persist independent specimen positions and reject a missing page position', function () {
    [$document, $owner] = pdfWorkflowFixture(twoPagePdfUpload());
    $cycle = $document->currentCycle();
    $positions = $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all();
    $positions[0]['specimen_scope'] = 'selected_pages';
    $positions[0]['specimen_pages'] = [1, 2];
    $positions[0]['specimen_positions'] = [
        1 => ['x' => 40, 'y' => 100, 'width' => 56.693, 'height' => 56.693],
        2 => ['x' => 300, 'y' => 500, 'width' => 56.693, 'height' => 56.693],
    ];
    $positions[1]['specimen_scope'] = 'selected_pages';
    $positions[1]['specimen_pages'] = [1, 2];
    $positions[1]['specimen_positions'] = [
        1 => ['x' => 400, 'y' => 100, 'width' => 56.693, 'height' => 56.693],
        2 => ['x' => 400, 'y' => 500, 'width' => 56.693, 'height' => 56.693],
    ];

    $this->actingAs($owner)->put(route('documents.pdf.place', $document), [
        'cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256,
        'confirmed' => 1,
        'positions' => $positions,
    ])->assertSessionHasNoErrors();

    $saved = $document->currentCycle()->signatures()->first()->fresh();
    expect((float) $saved->specimen_positions['1']['x'])->toBe(40.0);
    expect((float) $saved->specimen_positions['2']['x'])->toBe(300.0);
    expect((float) $saved->x)->toBe(40.0);

    $savedSecond = $document->currentCycle()->signatures()->skip(1)->firstOrFail()->fresh();
    expect(array_map('intval', $savedSecond->specimen_pages))->toBe([1, 2]);

    $duplicate = $positions;
    $duplicate[0]['specimen_pages'] = [1, 1];
    $this->actingAs($owner)->put(route('documents.pdf.place', $document), [
        'cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256,
        'confirmed' => 1,
        'positions' => $duplicate,
    ])->assertSessionHasErrors('positions.0.specimen_pages');

    $missing = $positions;
    unset($missing[0]['specimen_positions'][2]);
    $this->actingAs($owner)->put(route('documents.pdf.place', $document), [
        'cycle_token' => $cycle->public_id,
        'source_sha256' => $cycle->original_sha256,
        'confirmed' => 1,
        'positions' => $missing,
    ])->assertSessionHasErrors('positions');
});

test('draft PDF workflow guides the owner to confirm positions before submit', function () {
    [$document, $owner] = pdfWorkflowFixture();
    $document->currentCycle()->update(['positions_confirmed_at' => null]);

    $response = $this->actingAs($owner)->get(route('documents.show', $document));

    $response
        ->assertOk()
        ->assertSee('Draft belum siap diajukan')
        ->assertSee('Atur Posisi Spesiment')
        ->assertSee('Lengkapi posisi Spesiment')
        ->assertSee('Preview dokumen sumber')
        ->assertDontSee('Atur PDF & posisi QR')
        ->assertDontSee('Tinjau posisi Spesiment')
        ->assertDontSee('Ajukan Dokumen');

    expect(substr_count($response->getContent(), route('documents.pdf.edit', $document)))->toBe(1);
});

test('confirmed draft exposes one review position action', function () {
    [$document, $owner] = pdfWorkflowFixture();

    $response = $this->actingAs($owner)->get(route('documents.show', $document));

    $response
        ->assertOk()
        ->assertSee('Siap diajukan')
        ->assertSee('Tinjau posisi Spesiment')
        ->assertSee(route('documents.pdf.review', $document))
        ->assertDontSee('Preview dokumen sumber')
        ->assertDontSee('Atur PDF & posisi QR')
        ->assertDontSee(route('documents.pdf.edit', $document));

    expect(substr_count($response->getContent(), route('documents.pdf.review', $document)))->toBe(1);

    $this->actingAs($owner)->get(route('documents.pdf.review', $document))
        ->assertOk()
        ->assertSee('aria-label="Kembali ke detail dokumen"', false)
        ->assertSee('Preview read-only')
        ->assertSee('Edit posisi Spesiment')
        ->assertSee('Preview PDF tahap berjalan')
        ->assertSee(route('documents.pdf.review-file', $document))
        ->assertDontSee('Unduh PDF ini')
        ->assertDontSee('Simpan peserta & urutan')
        ->assertDontSee('Konfirmasi seluruh posisi');

    $this->actingAs($owner)->get(route('documents.pdf.review-file', $document))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('position review is unavailable before positions are confirmed', function () {
    [$document, $owner] = pdfWorkflowFixture();
    $document->currentCycle()->update(['positions_confirmed_at' => null]);

    $this->actingAs($owner)->get(route('documents.pdf.review', $document))->assertStatus(409);
});

test('newly created documents save the PDF workflow before redirecting to the document list', function () {
    [$source, $owner, $approvers, $signers] = pdfWorkflowFixture();
    WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $owner->id, 'type' => 'destination']);
    $this->actingAs($owner)->post(route('documents.store'), [
        'title' => 'Dokumen baru', 'destination_user_id' => $owner->id,
        'approver_id' => $approvers[0]->id, 'signer_id' => $signers[0]->id,
        'pdf' => new UploadedFile(base_path('tests/Fixtures/scanned.pdf'), 'fixture.pdf', 'application/pdf', null, true),
    ])->assertSessionHasNoErrors()->assertRedirect(route('documents.index'));
    $document = Document::where('title', 'Dokumen baru')->firstOrFail();
    expect($document->requires_pdf_workflow)->toBeTrue();
    expect($document->fresh()->workflow_cycle)->toBe(1);
});

test('configuration rejects users outside master duplicate participants and administrators', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    $this->actingAs($owner);
    $unknown = User::factory()->create();
    $payload = ['approvers' => [$unknown->id], 'signers' => array_column($signers, 'id')];
    $this->post(route('documents.pdf.store', $document), $payload)->assertSessionHasErrors('approver');
    $payload['approvers'] = [$approvers[0]->id, $approvers[0]->id];
    $this->post(route('documents.pdf.store', $document), $payload)->assertSessionHasErrors('approvers.0');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    WorkflowMasterEntry::create(['user_id' => $owner->id, 'target_user_id' => $admin->id, 'type' => 'approver']);
    $payload['approvers'] = [$admin->id];
    $this->post(route('documents.pdf.store', $document), $payload)->assertSessionHasErrors('approver');
    expect($document->currentCycle()->approvals()->count())->toBe(2);
});

test('non PDF and duplicate placeholders are rejected without replacing the source file', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    $originalHash = $document->currentCycle()->original_sha256;
    $payload = ['approvers' => array_column($approvers, 'id'), 'signers' => array_column($signers, 'id')];
    $this->actingAs($owner)->post(route('documents.pdf.store', $document), [
        ...$payload, 'pdf' => UploadedFile::fake()->createWithContent('fake.pdf', '<html>not a PDF</html>'),
    ])->assertSessionHasErrors('pdf');
    $this->post(route('documents.pdf.store', $document), [
        ...$payload, 'pdf' => new UploadedFile(base_path('tests/Fixtures/duplicate.pdf'), 'fixture.pdf', 'application/pdf', null, true),
    ])->assertSessionHasErrors('pdf');
    expect($document->currentCycle()->original_sha256)->toBe($originalHash);
});

test('prior approvers remain on dashboard and cannot be deleted from document history', function () {
    [$document, $owner, $approvers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    app(DocumentWorkflow::class)->decide($document, $approvers[0], $document->currentCycle()->public_id);
    $this->actingAs($approvers[0])->get(route('dashboard'))->assertSee($document->title);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->delete(route('admin.users.destroy', $approvers[0]))->assertSessionHas('error');
    $this->assertModelExists($approvers[0]);
});

test('mock endpoint rejects a real passphrase and does not flash it back', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    approvePdfWorkflow($document, $approvers);
    $this->actingAs($signers[0])->post(route('documents.sign', $document), [
        'cycle_token' => $document->currentCycle()->public_id, 'mock_acknowledged' => 1, 'passphrase' => 'never-store-this',
    ])->assertSessionHasErrors('passphrase')->assertSessionMissing('_old_input.passphrase');
    expect($document->currentCycle()->signatures()->first()->status)->toBe('pending');
});

test('intermediate file tampering prevents the next signer from finalizing', function () {
    [$document, $owner, $approvers, $signers] = pdfWorkflowFixture();
    submitPdfWorkflow($document, $owner);
    approvePdfWorkflow($document, $approvers);
    $token = $document->currentCycle()->public_id;
    app(DocumentWorkflow::class)->sign($document, $signers[0], $token);
    Storage::disk('local')->append($document->currentCycle()->current_path, 'tampered');
    $this->actingAs($signers[1])->post(route('documents.sign', $document), ['cycle_token' => $token, 'mock_acknowledged' => 1, 'passphrase' => 'MOCK-SIGNWORK-2026'])->assertSessionHasErrors('pdf');
    expect($document->currentCycle()->final_sha256)->toBeNull();
    expect($document->currentCycle()->signatures()->where('sequence', 2)->first()->status)->toBe('pending');
});

test('QR only dimensions are fixed and unknown specimen formats are rejected', function () {
    [$document, $owner] = pdfWorkflowFixture();
    $cycle = $document->currentCycle();
    $positions = $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all();
    $positions[0]['width'] = 85.04;
    $positions[0]['height'] = 56.69;
    $payload = ['cycle_token' => $cycle->public_id, 'source_sha256' => $cycle->original_sha256, 'confirmed' => 1, 'positions' => $positions];

    $this->actingAs($owner)->put(route('documents.pdf.place', $document), $payload)->assertSessionHasNoErrors();

    $step = $cycle->signatures()->orderBy('sequence')->firstOrFail();
    expect((float) $step->width)->toBe(56.693);
    expect((float) $step->height)->toBe(56.693);

    $payload['positions'][0]['specimen_format'] = 'custom';
    $this->put(route('documents.pdf.place', $document), $payload)->assertSessionHasErrors('positions.0.specimen_format');
    expect((float) $step->fresh()->width)->toBe(56.693);
});
