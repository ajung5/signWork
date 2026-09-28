<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignApproverRequest;
use App\Http\Requests\AssignSignerRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowController extends Controller
{
    public function __construct(private DocumentWorkflow $workflow) {}

    public function submit(
        Document $document,
        Request $request
    ): RedirectResponse {
        Gate::authorize(
            'view',
            $document
        );

        abort_unless($document->isDraft(), 409, 'Dokumen sudah tidak berada pada status Draft.');

        Gate::authorize(
            'submit',
            $document
        );

        if ($document->requires_pdf_workflow && $document->workflow_cycle === 0) {
            throw ValidationException::withMessages(['pdf' => 'Unggah PDF, atur alur, dan konfirmasi posisi QR terlebih dahulu.']);
        }
        if ($document->workflow_cycle > 0) {
            $data = $request->validate(['cycle_token' => ['required', 'uuid']]);
            $this->workflow->submit($document, $request->user(), $data['cycle_token']);
            $document->refresh();
        } else {
            $document->submit();
        }

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                $document->isWaitingApproval()
                    ? 'Dokumen berhasil diajukan dan menunggu verifikasi.'
                    : 'Dokumen berhasil diajukan.'
            );
    }

    public function assignApprover(
        AssignApproverRequest $request,
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'assignApprover',
            $document
        );

        $approver = User::query()
            ->findOrFail(
                $request->integer(
                    'approver_id'
                )
            );

        $document->assignApprover(
            $approver
        );

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                'Verifikator berhasil ditetapkan.'
            );
    }

    public function approve(
        Request $request,
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'view',
            $document
        );

        if (
            ! $document->isWaitingApproval()
        ) {
            return redirect()
                ->route(
                    'documents.show',
                    $document
                )
                ->with(
                    'info',
                    'Opsi verifikasi sudah dikunci karena keputusan dokumen telah diproses.'
                );
        }

        Gate::authorize(
            'approve',
            $document
        );

        if ($document->workflow_cycle > 0) {
            $data = $request->validate(['cycle_token' => ['required', 'uuid']]);
            $this->workflow->decide($document, $request->user(), $data['cycle_token']);
            $document->refresh();
        } else {
            $document->approveBy($request->user());
        }

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                $document->isWaitingSignature()
                    ? 'Dokumen diverifikasi dan diteruskan ke Signer.'
                    : 'Dokumen berhasil diverifikasi.'
            );
    }

    public function reject(
        Request $request,
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'view',
            $document
        );

        if (
            ! $document->isWaitingApproval()
        ) {
            return redirect()
                ->route(
                    'documents.show',
                    $document
                )
                ->with(
                    'info',
                    'Opsi verifikasi sudah dikunci karena keputusan dokumen telah diproses.'
                );
        }

        Gate::authorize(
            'reject',
            $document
        );

        $validated = $request->validate(
            [
                'rejection_reason' => [
                    'required',
                    'string',
                    'min:5',
                    'max:2000',
                ],
            ],
            [
                'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
                'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            ]
        );

        if ($document->workflow_cycle > 0) {
            $data = $request->validate(['cycle_token' => ['required', 'uuid']]);
            $this->workflow->decide($document, $request->user(), $data['cycle_token'], $validated['rejection_reason']);
        } else {
            $document->rejectBy($request->user(), $validated['rejection_reason']);
        }

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                'Dokumen ditolak dan dikembalikan kepada pembuat untuk diperbaiki.'
            );
    }

    public function revise(
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'revise',
            $document
        );

        return redirect()
            ->route(
                'documents.edit',
                $document
            )
            ->with(
                'info',
                'Silakan perbaiki dokumen. Status akan kembali menjadi Draft setelah perubahan disimpan.'
            );
    }

    public function assignSigner(
        AssignSignerRequest $request,
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'assignSigner',
            $document
        );

        $signer = User::query()
            ->findOrFail(
                $request->integer(
                    'signer_id'
                )
            );

        $document->assignSigner(
            $signer
        );

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                'Signer berhasil ditetapkan. Dokumen menunggu tanda tangan.'
            );
    }
}
