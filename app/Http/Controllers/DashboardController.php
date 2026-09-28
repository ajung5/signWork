<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->adminDashboard();
        }

        $documentsQuery = Document::query()
            ->where(
                function (Builder $scope) use ($user): void {
                    $scope
                        ->where(
                            'owner_id',
                            $user->id
                        )
                        ->orWhere(fn ($query) => $query->where('destination_user_id', $user->id)->where('status', DocumentStatus::Signed)->whereNotNull('sent_at'))
                        ->orWhere(
                            'approver_id',
                            $user->id
                        )
                        ->orWhere(
                            'signer_id',
                            $user->id
                        )
                        ->orWhereHas('cycles', fn ($query) => $query->whereColumn('number', 'documents.workflow_cycle')->whereHas('approvals', fn ($steps) => $steps->where('user_id', $user->id)))
                        ->orWhereHas('cycles', fn ($query) => $query->whereColumn('number', 'documents.workflow_cycle')->whereHas('signatures', fn ($steps) => $steps->where('user_id', $user->id)));
                }
            );

        $totalDocuments =
            (clone $documentsQuery)->count();

        $draftDocuments =
            (clone $documentsQuery)
                ->where(
                    'status',
                    DocumentStatus::Draft->value
                )
                ->count();

        $submittedDocuments =
            (clone $documentsQuery)
                ->where(
                    'status',
                    DocumentStatus::Submitted->value
                )
                ->count();

        $waitingApprovalDocuments =
            (clone $documentsQuery)
                ->where(
                    'status',
                    DocumentStatus::WaitingApproval->value
                )
                ->count();

        $processedDocuments =
            (clone $documentsQuery)
                ->whereIn(
                    'status',
                    [
                        DocumentStatus::Approved->value,
                        DocumentStatus::Rejected->value,
                        DocumentStatus::WaitingSignature->value,
                        DocumentStatus::Signing->value,
                        DocumentStatus::Signed->value,
                        DocumentStatus::SignFailed->value,
                    ]
                )
                ->count();

        $latestDocuments =
            (clone $documentsQuery)
                ->with([
                    'owner',
                    'destination',
                    'approver',
                    'signer',
                ])
                ->latest('updated_at')
                ->limit(5)
                ->get();

        $taskDocuments =
            (clone $documentsQuery)
                ->where(
                    function (Builder $scope) use ($user): void {
                        $scope
                            ->where(
                                function (Builder $query) use ($user): void {
                                    $query
                                        ->where('owner_id', $user->id)
                                        ->whereIn('status', [
                                            DocumentStatus::Draft->value,
                                            DocumentStatus::Rejected->value,
                                        ]);
                                }
                            )
                            ->orWhere(
                                function (Builder $query) use ($user): void {
                                    $query
                                        ->where('status', DocumentStatus::WaitingApproval->value)
                                        ->where('approver_id', $user->id);
                                }
                            )
                            ->orWhere(
                                function (Builder $query) use ($user): void {
                                    $query
                                        ->where('status', DocumentStatus::WaitingSignature->value)
                                        ->where('signer_id', $user->id);
                                }
                            )
                            ->orWhere(
                                function (Builder $query) use ($user): void {
                                    $query
                                        ->where('status', DocumentStatus::Signed->value)
                                        ->where(function (Builder $signed) use ($user): void {
                                            $signed
                                                ->where(function (Builder $owner) use ($user): void {
                                                    $owner
                                                        ->where('owner_id', $user->id)
                                                        ->whereNull('sent_at');
                                                })
                                                ->orWhere(function (Builder $destination) use ($user): void {
                                                    $destination
                                                        ->where('destination_user_id', $user->id)
                                                        ->whereNotNull('sent_at');
                                                })
                                                ->orWhere(function (Builder $lastSigner) use ($user): void {
                                                    $lastSigner
                                                        ->whereNull('sent_at')
                                                        ->whereHas(
                                                            'cycles',
                                                            fn (Builder $cycle): Builder => $cycle
                                                                ->whereColumn('number', 'documents.workflow_cycle')
                                                                ->whereHas(
                                                                    'signatures',
                                                                    fn (Builder $steps): Builder => $steps->where(
                                                                        'user_id',
                                                                        $user->id
                                                                    )
                                                                )
                                                        );
                                                });
                                        });
                                }
                            );
                    }
                )
                ->with(['owner', 'destination'])
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Document $document): ?array => $this->taskFor($document, $user))
                ->filter()
                ->values();

        $distribution =
            $this->buildDistribution(
                $totalDocuments,
                $draftDocuments,
                $submittedDocuments,
                $waitingApprovalDocuments,
                $processedDocuments
            );

        return view(
            'dashboard.index',
            [
                'totalDocuments' => $totalDocuments,
                'draftDocuments' => $draftDocuments,
                'submittedDocuments' => $submittedDocuments,
                'waitingApprovalDocuments' => $waitingApprovalDocuments,
                'processedDocuments' => $processedDocuments,
                'latestDocuments' => $latestDocuments,
                'taskDocuments' => $taskDocuments,
                'distribution' => $distribution,
            ]
        );
    }

    /**
     * @return array{document: Document, label: string, description: string, url: string}|null
     */
    private function taskFor(Document $document, User $user): ?array
    {
        if ($document->owner_id === $user->id && $document->isRejected()) {
            return [
                'document' => $document,
                'label' => 'Perbaiki dokumen',
                'description' => 'Dokumen dikembalikan dan perlu diperbaiki sebelum diajukan ulang.',
                'url' => route('documents.edit', $document),
            ];
        }

        if ($document->owner_id === $user->id && $document->isDraft()) {
            if (! $document->requires_pdf_workflow) {
                return [
                    'document' => $document,
                    'label' => 'Edit dokumen',
                    'description' => 'Lengkapi informasi dokumen sebelum mengajukannya.',
                    'url' => route('documents.edit', $document),
                ];
            }

            $cycle = $document->currentCycle();

            if (! $cycle || ! $cycle->positions_confirmed_at) {
                return [
                    'document' => $document,
                    'label' => 'Atur dokumen',
                    'description' => 'Lengkapi PDF, peserta, dan posisi spesimen.',
                    'url' => route('documents.pdf.edit', $document),
                ];
            }

            return [
                'document' => $document,
                'label' => 'Ajukan dokumen',
                'description' => 'Dokumen siap masuk ke tahap verifikasi.',
                'url' => route('documents.show', $document).'#workflow-action',
            ];
        }

        if ($document->isWaitingApproval() && $document->approver_id === $user->id) {
            return [
                'document' => $document,
                'label' => 'Verifikasi dokumen',
                'description' => 'Periksa isi dokumen lalu setujui atau kembalikan.',
                'url' => route('documents.show', $document).'#workflow-action',
            ];
        }

        if ($document->isWaitingSignature() && $document->signer_id === $user->id) {
            return [
                'document' => $document,
                'label' => 'Tanda tangan dokumen',
                'description' => 'Dokumen menunggu tanda tangan Anda.',
                'url' => route('documents.show', $document).'#workflow-action',
            ];
        }

        if ($document->isSigned() && Gate::forUser($user)->allows('send', $document)) {
            return [
                'document' => $document,
                'label' => 'Kirim dokumen final',
                'description' => 'Dokumen sudah ditandatangani dan siap dikirim ke penerima.',
                'url' => route('documents.show', $document).'#workflow-action',
            ];
        }

        if (
            $document->isSigned()
            && $document->destination_user_id === $user->id
            && $document->sent_at !== null
            && ($cycle = $document->currentCycle()) !== null
        ) {
            return [
                'document' => $document,
                'label' => 'Buka verifikasi QR',
                'description' => 'Dokumen final sudah masuk ke dokumen masuk Anda.',
                'url' => route('verification.show', $cycle->public_id),
            ];
        }

        return null;
    }

    private function adminDashboard(): View
    {
        $rawStatusCounts =
            Document::query()
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        $statusSummary = collect(
            DocumentStatus::cases()
        )
            ->map(
                fn (
                    DocumentStatus $status
                ): array => [
                    'status' => $status,
                    'count' => (int) (
                        $rawStatusCounts[
                            $status->value
                        ] ?? 0
                    ),
                ]
            );

        $totalDocuments =
            Document::query()->count();

        $draftDocuments =
            (int) (
                $rawStatusCounts[
                    DocumentStatus::Draft->value
                ] ?? 0
            );

        $submittedDocuments =
            (int) (
                $rawStatusCounts[
                    DocumentStatus::Submitted->value
                ] ?? 0
            );

        $waitingApprovalDocuments =
            (int) (
                $rawStatusCounts[
                    DocumentStatus::WaitingApproval->value
                ] ?? 0
            );

        $processedDocuments =
            max(
                0,
                $totalDocuments
                - $draftDocuments
                - $submittedDocuments
                - $waitingApprovalDocuments
            );

        $latestDocuments =
            Document::query()
                ->with([
                    'owner',
                    'destination',
                    'approver',
                    'signer',
                ])
                ->latest('updated_at')
                ->limit(10)
                ->get();

        return view(
            'dashboard.admin',
            [
                'totalDocuments' => $totalDocuments,
                'totalUsers' => User::query()->count(),
                'regularUsers' => User::query()
                    ->where(
                        'role',
                        UserRole::User->value
                    )
                    ->count(),
                'adminUsers' => User::query()
                    ->whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])
                    ->count(),
                'superadminUsers' => User::query()->where('role', UserRole::Superadmin->value)->count(),
                'statusSummary' => $statusSummary,
                'latestDocuments' => $latestDocuments,
                'distribution' => $this->buildDistribution(
                    $totalDocuments,
                    $draftDocuments,
                    $submittedDocuments,
                    $waitingApprovalDocuments,
                    $processedDocuments
                ),
            ]
        );
    }

    /**
     * @return array{
     *     draft: float,
     *     submitted: float,
     *     waiting: float,
     *     processed: float
     * }
     */
    private function buildDistribution(
        int $total,
        int $draft,
        int $submitted,
        int $waiting,
        int $processed
    ): array {
        if ($total === 0) {
            return [
                'draft' => 0,
                'submitted' => 0,
                'waiting' => 0,
                'processed' => 0,
            ];
        }

        return [
            'draft' => round(
                ($draft / $total) * 100,
                2
            ),
            'submitted' => round(
                ($submitted / $total) * 100,
                2
            ),
            'waiting' => round(
                ($waiting / $total) * 100,
                2
            ),
            'processed' => round(
                ($processed / $total) * 100,
                2
            ),
        ];
    }
}
