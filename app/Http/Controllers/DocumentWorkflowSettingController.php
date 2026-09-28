<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Http\Requests\StoreWorkflowMasterEntryRequest;
use App\Http\Requests\UpdateWorkflowMasterEntryRequest;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentWorkflowSettingController extends Controller
{
    public function edit(
        Request $request,
        ?string $type = null
    ): View|RedirectResponse {
        $this->authorizeUser(
            $request
        );

        if ($type === null) {
            return to_route('workflow-settings.group', ['type' => 'signer']);
        }

        $selectedType = WorkflowMasterType::tryFrom($type);
        abort_unless($selectedType !== null, 404);

        $search = $request->string('q')->trim()->toString();
        $assignedUserIds = $request->user()->workflowMasterEntries()
            ->where('type', $selectedType->value)
            ->pluck('target_user_id');

        $entries = $request->user()->workflowMasterEntries()
            ->where('type', $selectedType->value)
            ->with('target')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('target', function (Builder $target) use ($search): void {
                    $target->where(function (Builder $fields) use ($search): void {
                        $fields->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view(
            'workflow-settings.edit',
            [
                'entries' => $entries,
                'users' => $this->workflowUsers()->whereNotIn('id', $assignedUserIds),
                'search' => $search,
                'types' => [$selectedType],
                'selectedType' => $selectedType,
            ]
        );
    }

    public function store(
        StoreWorkflowMasterEntryRequest $request
    ): RedirectResponse {
        $type =
            WorkflowMasterType::from(
                $request->string(
                    'type'
                )->toString()
            );

        DB::transaction(
            function () use (
                $request,
                $type
            ): void {
                $hasEntry =
                    $request->user()
                        ->workflowMasterEntries()
                        ->where(
                            'type',
                            $type->value
                        )
                        ->exists();

                $makeDefault =
                    $request->boolean(
                        'is_default'
                    )
                    || ! $hasEntry;

                if ($makeDefault) {
                    $this->clearDefault(
                        $request->user()->id,
                        $type
                    );
                }

                WorkflowMasterEntry::query()
                    ->create([
                        'user_id' => $request->user()->id,
                        'type' => $type,
                        'target_user_id' => $request->integer(
                            'target_user_id'
                        ),
                        'is_default' => $makeDefault,
                    ]);
            }
        );

        return redirect()
            ->route(
                'workflow-settings.group',
                ['type' => $type->value]
            )
            ->with(
                'success',
                'Data Master berhasil ditambahkan.'
            );
    }

    public function editEntry(
        Request $request,
        WorkflowMasterEntry $workflowMasterEntry
    ): View {
        $this->authorizeEntry(
            $request,
            $workflowMasterEntry
        );

        return view(
            'workflow-settings.entry-edit',
            [
                'entry' => $workflowMasterEntry
                    ->load('target'),
                'users' => $this->workflowUsers(),
                'types' => WorkflowMasterType::cases(),
                'assignedUsers' => $request->user()->workflowMasterEntries()
                    ->where('id', '!=', $workflowMasterEntry->id)
                    ->get(['type', 'target_user_id'])
                    ->groupBy(fn (WorkflowMasterEntry $entry): string => $entry->type->value)
                    ->map(fn ($entries) => $entries->pluck('target_user_id')->values()),
            ]
        );
    }

    public function updateEntry(
        UpdateWorkflowMasterEntryRequest $request,
        WorkflowMasterEntry $workflowMasterEntry
    ): RedirectResponse {
        $oldType =
            $workflowMasterEntry->type;

        $newType =
            WorkflowMasterType::from(
                $request->string(
                    'type'
                )->toString()
            );

        DB::transaction(
            function () use (
                $request,
                $workflowMasterEntry,
                $oldType,
                $newType
            ): void {
                $makeDefault =
                    $request->boolean(
                        'is_default'
                    );

                if ($makeDefault) {
                    $this->clearDefault(
                        $request->user()->id,
                        $newType,
                        $workflowMasterEntry->id
                    );
                }

                $workflowMasterEntry
                    ->update([
                        'type' => $newType,
                        'target_user_id' => $request->integer(
                            'target_user_id'
                        ),
                        'is_default' => $makeDefault,
                    ]);

                $this->ensureDefault(
                    $request->user()->id,
                    $newType
                );

                if (
                    $oldType !== $newType
                ) {
                    $this->ensureDefault(
                        $request->user()->id,
                        $oldType
                    );
                }
            }
        );

        return redirect()
            ->route(
                'workflow-settings.group',
                ['type' => $newType->value]
            )
            ->with(
                'success',
                'Data Master berhasil diperbarui.'
            );
    }

    public function destroyEntry(
        Request $request,
        WorkflowMasterEntry $workflowMasterEntry
    ): RedirectResponse {
        $this->authorizeEntry(
            $request,
            $workflowMasterEntry
        );

        $type =
            $workflowMasterEntry->type;

        DB::transaction(
            function () use (
                $request,
                $workflowMasterEntry,
                $type
            ): void {
                $workflowMasterEntry
                    ->delete();

                $this->ensureDefault(
                    $request->user()->id,
                    $type
                );
            }
        );

        return redirect()
            ->route(
                'workflow-settings.group',
                ['type' => $type->value]
            )
            ->with(
                'success',
                'Data Master berhasil dihapus.'
            );
    }

    private function ensureDefault(
        int $userId,
        WorkflowMasterType $type
    ): void {
        $query =
            WorkflowMasterEntry::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'type',
                    $type->value
                );

        if (
            (clone $query)
                ->where(
                    'is_default',
                    true
                )
                ->exists()
        ) {
            return;
        }

        $entry =
            $query
                ->orderBy('id')
                ->first();

        $entry?->update([
            'is_default' => true,
        ]);
    }

    private function clearDefault(
        int $userId,
        WorkflowMasterType $type,
        ?int $exceptId = null
    ): void {
        WorkflowMasterEntry::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'type',
                $type->value
            )
            ->when(
                $exceptId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $exceptId
                )
            )
            ->update([
                'is_default' => false,
            ]);
    }

    private function workflowUsers()
    {
        return User::query()
            ->where(
                'role',
                UserRole::User->value
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);
    }

    private function authorizeUser(
        Request $request
    ): void {
        abort_if(
            $request->user()?->isAdmin(),
            403
        );
    }

    private function authorizeEntry(
        Request $request,
        WorkflowMasterEntry $entry
    ): void {
        $this->authorizeUser(
            $request
        );

        abort_unless(
            $entry->user_id
                === $request->user()->id,
            403
        );
    }
}
