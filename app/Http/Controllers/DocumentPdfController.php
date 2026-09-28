<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigureDocumentWorkflowRequest;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentWorkflow;
use App\Services\PdfEngine;
use App\Services\SpecimenTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentPdfController extends Controller {
    public function edit(Document $document): View {
        Gate::authorize('update', $document);
        $cycle = $document->currentCycle()?->load(['approvals', 'signatures']);
        $owner = $document->owner;
        $participants = [];
        foreach (['approver', 'signer'] as $type) {
            $existing = $cycle
                ? ($type === 'approver' ? $cycle->approvals : $cycle->signatures)->pluck('user_id')->all()
                : [];
            $existing[] = $type === 'approver' ? $document->approver_id : $document->signer_id;
            $ids = $owner
                ->workflowMasterEntries()
                ->where('type', $type)
                ->pluck('target_user_id')
                ->merge($existing)
                ->push($owner->id)
                ->filter()
                ->unique();
            $participants[$type] = User::query()
                ->whereIn('id', $ids)
                ->whereNotIn('role', [UserRole::Admin->value, UserRole::Superadmin->value])
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $specimenSteps = $cycle && $document->isDraft() ? app(SpecimenTemplate::class)->options($cycle) : [];

        return view('documents.pdf-workflow', compact('document', 'cycle', 'participants', 'specimenSteps'));
    }

    public function review(Document $document): View {
        Gate::authorize('view', $document);
        $cycle = $document->currentCycle()?->load('signatures');
        abort_unless($cycle?->original_path && $cycle->positions_confirmed_at, 409, 'Posisi spesimen belum dikonfirmasi.');

        return view('documents.pdf-review', compact('document', 'cycle'));
    }

    public function reviewFile(Document $document, PdfEngine $pdf): BinaryFileResponse {
        Gate::authorize('view', $document);
        $cycle = $document->currentCycle()?->load('signatures');
        abort_unless($cycle?->original_path && $cycle->positions_confirmed_at, 409, 'Posisi spesimen belum dikonfirmasi.');

        if ($cycle->prepared_path && $cycle->prepared_sha256) {
            $pdf->ensureHash($cycle->prepared_path, $cycle->prepared_sha256);

            return response()->file($pdf->path($cycle->prepared_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename=SignWork-review.pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN'
            ]);
        }

        $pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
        $path = 'signwork/previews/' . Str::uuid() . '-review.pdf';
        Storage::disk('local')->makeDirectory('signwork/previews');

        try {
            $verificationUrl = rtrim((string) config('signwork.verification_base_url'), '/') .
                route('verification.show', $cycle->public_id, false);
            $pdf->run('prepare', $cycle->original_path, [
                'output' => $pdf->path($path),
                'steps' => $cycle->signatures->toArray(),
                'verification_url' => $verificationUrl,
                'specimen_version' => (int) $cycle->specimen_version
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return response()
            ->file($pdf->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename=SignWork-review.pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN'
            ])
            ->deleteFileAfterSend();
    }

    public function store(
        ConfigureDocumentWorkflowRequest $request,
        Document $document,
        DocumentWorkflow $workflow
    ): RedirectResponse {
        $workflow->configure(
            $document,
            $request->user(),
            array_map('intval', $request->validated('approvers')),
            array_map('intval', $request->validated('signers')),
            $request->file('pdf')
        );

        return to_route('documents.pdf.edit', $document)->with(
            'success',
            'PDF dan alur disimpan. Periksa lalu konfirmasi seluruh posisi QR.'
        );
    }

    public function place(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse {
        Gate::authorize('update', $document);
        $data = $request->validate([
            'cycle_token' => ['required', 'uuid'],
            'source_sha256' => ['required', 'size:64'],
            'confirmed' => ['accepted'],
            'positions' => ['required', 'array', 'min:1', 'max:10'],
            'positions.*.specimen_format' => ['nullable', 'in:framed,qr_2cm,qr_3cm,qr_2x2,qr_3x3'],
            'positions.*.specimen_scope' => ['nullable', 'in:all_pages,selected_pages,selected_page'],
            'positions.*.specimen_pages' => ['nullable', 'array', 'max:100'],
            'positions.*.specimen_pages.*' => ['integer', 'min:1'],
            'positions.*.specimen_positions' => ['nullable', 'array', 'max:100'],
            'positions.*.specimen_positions.*.x' => ['required', 'numeric', 'min:0', 'max:2500'],
            'positions.*.specimen_positions.*.y' => ['required', 'numeric', 'min:0', 'max:2500'],
            'positions.*.specimen_positions.*.width' => ['required', 'numeric', 'min:1', 'max:400'],
            'positions.*.specimen_positions.*.height' => ['required', 'numeric', 'min:1', 'max:300'],
            'positions.*.profile_fingerprint' => ['nullable', 'string', 'size:64'],
            'positions.*.id' => ['required', 'integer', 'distinct'],
            'positions.*.page' => ['required', 'integer', 'min:1'],
            'positions.*.x' => ['required', 'numeric', 'min:0', 'max:2500'],
            'positions.*.y' => ['required', 'numeric', 'min:0', 'max:2500'],
            'positions.*.width' => ['required', 'numeric', 'min:1', 'max:400'],
            'positions.*.height' => ['required', 'numeric', 'min:1', 'max:300']
        ]);
        foreach ($data['positions'] as $index => $position) {
            $pages = array_map('intval', $position['specimen_pages'] ?? []);
            if (count($pages) !== count(array_unique($pages))) {
                throw ValidationException::withMessages([
                    "positions.{$index}.specimen_pages" => 'Halaman spesimen tidak boleh berulang pada signer yang sama.'
                ]);
            }
        }
        $workflow->place(
            $document,
            $request->user(),
            $data['cycle_token'],
            $data['source_sha256'],
            array_values($data['positions'])
        );

        return to_route('documents.show', $document)->with('success', 'Posisi QR dikonfirmasi. Dokumen siap diajukan.');
    }

    public function preview(Request $request, Document $document, PdfEngine $pdf): BinaryFileResponse {
        Gate::authorize('view', $document);
        $data = $request->validate(['page' => ['required', 'integer', 'min:1']]);
        $cycle = $document->currentCycle();
        abort_unless($cycle?->original_path, 404);
        $pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
        $path = 'signwork/previews/' . Str::uuid() . '.png';
        Storage::disk('local')->makeDirectory('signwork/previews');
        try {
            $pdf->run('render', $cycle->original_path, [
                'page' => (int) $data['page'],
                'output' => $pdf->path($path),
                'specimen_version' => $document->isDraft() ? 1 : (int) $cycle->specimen_version
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return response()
            ->file($pdf->path($path), [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff'
            ])
            ->deleteFileAfterSend();
    }

    public function download(Request $request, Document $document, PdfEngine $pdf): BinaryFileResponse {
        Gate::authorize('view', $document);
        $cycle = $document->currentCycle();
        abort_unless($cycle, 404);
        $request->validate(['version' => ['nullable', 'in:original,prepared,current,final']]);
        $version = $request->query('version', 'original');
        $final = $version === 'final';
        abort_if($final && $cycle->status !== 'signed', 409, 'PDF final belum tersedia.');
        [$path, $hash] = match ($version) {
            'final' => [$cycle->current_path, $cycle->final_sha256],
            'current' => [$cycle->current_path, $cycle->current_sha256],
            'prepared' => [$cycle->prepared_path, $cycle->prepared_sha256],
            default => [$cycle->original_path, $cycle->original_sha256]
        };
        abort_unless($path && $hash, 404);
        $pdf->ensureHash($path, $hash);

        if ($request->boolean('inline')) {
            return response()->file($pdf->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename=SignWork-preview.pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN'
            ]);
        }

        return response()->download(
            $pdf->path($path),
            'SignWork-' . ($version === 'original' ? 'original' : 'MOCK-' . $version) . '.pdf',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }

    public function viewer(Request $request, Document $document): View {
        Gate::authorize('view', $document);
        $data = $request->validate(['version' => ['nullable', 'in:original,prepared,current,final']]);
        $cycle = $document->currentCycle();
        abort_unless($cycle?->original_path, 404);
        $version = $data['version'] ?? 'original';
        abort_if($version === 'final' && $cycle->status !== 'signed', 409);

        return view('documents.pdf-viewer', compact('document', 'cycle', 'version'));
    }

    public function send(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse {
        $data = $request->validate(['cycle_token' => ['required', 'uuid']]);
        $workflow->send($document, $request->user(), $data['cycle_token']);

        return to_route('documents.show', $document)->with('success', 'Dokumen final berhasil dikirim ke penerima.');
    }

    public function sign(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse {
        Gate::authorize('sign', $document);
        $data = $request->validate([
            'cycle_token' => ['required', 'uuid'],
            'mock_acknowledged' => ['accepted'],
            'passphrase' => ['required', 'string', 'max:200']
        ]);
        if (config('signwork.provider') !== 'mock') {
            throw ValidationException::withMessages(['provider' => 'Provider BSrE belum tersedia.']);
        }
        if (!hash_equals((string) config('signwork.mock_passphrase'), $data['passphrase'])) {
            return back()
                ->withErrors([
                    'passphrase' => 'Passphrase simulasi salah. Dokumen belum ditandatangani.'
                ])
                ->withInput($request->except('passphrase'));
        }
        $workflow->sign($document, $request->user(), $data['cycle_token']);

        return to_route('documents.show', $document)->with(
            'success',
            'Passphrase simulasi berhasil diverifikasi. Tahap tanda tangan berhasil. Ini bukan TTE BSrE.'
        );
    }
}
