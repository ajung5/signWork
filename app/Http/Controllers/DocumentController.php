<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\WorkflowMasterType;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(
        Request $request
    ): View {
        Gate::authorize(
            'viewAny',
            Document::class
        );

        $documents = Document::query()
            ->where(
                'owner_id',
                $request->user()->id
            )
            ->with([
                'owner',
                'destination',
                'approver',
                'signer',
            ])
            ->latest()
            ->paginate(10);

        return view(
            'documents.index',
            [
                'documents' => $documents,
            ]
        );
    }

    public function create(
        Request $request
    ): View {
        Gate::authorize(
            'create',
            Document::class
        );

        $owner = $request->user();

        return view(
            'documents.create',
            [
                'destinationUsers' => $this->masterUsers(
                    $owner,
                    WorkflowMasterType::Destination
                ),
                'approverUsers' => $this->masterUsers(
                    $owner,
                    WorkflowMasterType::Approver
                ),
                'signerUsers' => $this->masterUsers(
                    $owner,
                    WorkflowMasterType::Signer
                ),
                'defaults' => [
                    'destination_user_id' => $this->defaultTargetId(
                        $owner,
                        WorkflowMasterType::Destination
                    ),
                    'approver_id' => $this->defaultTargetId(
                        $owner,
                        WorkflowMasterType::Approver
                    ),
                    'signer_id' => $this->defaultTargetId(
                        $owner,
                        WorkflowMasterType::Signer
                    ),
                ],
            ]
        );
    }

    public function store(
        StoreDocumentRequest $request
    ): RedirectResponse {
        Gate::authorize(
            'create',
            Document::class
        );

        $document =
            $request->user()
                ->documents()
                ->create([
                    ...$request->validated(),
                    'status' => DocumentStatus::Draft,
                ]);

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                'Dokumen berhasil dibuat.'
            );
    }

    public function show(
        Document $document
    ): View {
        Gate::authorize(
            'view',
            $document
        );

        $document->load([
            'owner',
            'destination',
            'approver',
            'signer',
        ]);

        $approverUsers = collect();
        $signerUsers = collect();

        if (
            Gate::allows(
                'assignApprover',
                $document
            )
        ) {
            $approverUsers =
                $this->masterUsers(
                    $document->owner,
                    WorkflowMasterType::Approver,
                    $document->approver
                );
        }

        if (
            Gate::allows(
                'assignSigner',
                $document
            )
        ) {
            $signerUsers =
                $this->masterUsers(
                    $document->owner,
                    WorkflowMasterType::Signer,
                    $document->signer
                );
        }

        return view(
            'documents.show',
            [
                'document' => $document,
                'approverUsers' => $approverUsers,
                'signerUsers' => $signerUsers,
                'cycle' => $document->currentCycle()?->load(['approvals', 'signatures']),
                'cycles' => $document->cycles()->with(['approvals', 'signatures'])->get(),
            ]
        );
    }

    public function edit(
        Document $document
    ): View {
        Gate::authorize(
            'update',
            $document
        );

        $document->load([
            'owner',
            'destination',
            'approver',
            'signer',
        ]);

        return view(
            'documents.edit',
            [
                'document' => $document,
                'destinationUsers' => $this->masterUsers(
                    $document->owner,
                    WorkflowMasterType::Destination,
                    $document->destination
                ),
                'approverUsers' => $this->masterUsers(
                    $document->owner,
                    WorkflowMasterType::Approver,
                    $document->approver
                ),
                'signerUsers' => $this->masterUsers(
                    $document->owner,
                    WorkflowMasterType::Signer,
                    $document->signer
                ),
            ]
        );
    }

    public function update(
        UpdateDocumentRequest $request,
        Document $document,
        DocumentWorkflow $workflow
    ): RedirectResponse {
        Gate::authorize(
            'update',
            $document
        );

        if ($document->workflow_cycle > 0) {
            $workflow->updateMetadata($document, $request->user(), $request->validated());

            return to_route('documents.pdf.edit', $document)->with('success', 'Metadata disimpan. Periksa alur dan posisi sebelum mengajukan.');
        }

        $wasRejected =
            $document->isRejected();

        if ($wasRejected) {
            $document->revise();
        }

        $document->update(
            $request->validated()
        );

        return redirect()
            ->route(
                'documents.show',
                $document
            )
            ->with(
                'success',
                $wasRejected
                    ? 'Perbaikan berhasil disimpan. Dokumen kembali menjadi Draft dan dapat diajukan ulang.'
                    : 'Dokumen berhasil diperbarui.'
            );
    }

    public function destroy(
        Document $document
    ): RedirectResponse {
        Gate::authorize(
            'delete',
            $document
        );

        $paths = DB::transaction(function () use ($document): array {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::authorize('delete', $locked);
            $paths = $locked->cycles()->get()->flatMap(fn ($cycle) => [$cycle->original_path, $cycle->source_path])->filter()->unique()->all();
            $locked->delete();

            return $paths;
        });
        Storage::disk('local')->delete($paths);

        return redirect()
            ->route(
                'documents.index'
            )
            ->with(
                'success',
                'Dokumen berhasil dihapus.'
            );
    }

    private function masterUsers(
        User $owner,
        WorkflowMasterType $type,
        ?User $current = null
    ): Collection {
        $users = User::query()
            ->whereIn(
                'id',
                $owner
                    ->workflowMasterEntries()
                    ->where(
                        'type',
                        $type->value
                    )
                    ->pluck(
                        'target_user_id'
                    )->push($owner->id)
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        if (
            $current !== null
            && ! $users->contains(
                'id',
                $current->id
            )
        ) {
            $users->push($current);
        }

        return $users
            ->sortBy('name')
            ->values();
    }

    private function defaultTargetId(
        User $owner,
        WorkflowMasterType $type
    ): ?int {
        $entry = $owner
            ->workflowMasterEntries()
            ->where(
                'type',
                $type->value
            )
            ->orderByDesc(
                'is_default'
            )
            ->orderBy('id')
            ->first();

        return $entry?->target_user_id;
    }
}
