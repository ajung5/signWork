<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDocumentController extends Controller
{
    public function index(
        Request $request
    ): View {
        abort_unless(
            $request->user()?->isAdmin(),
            403
        );

        $search =
            $request->string('q')
                ->trim()
                ->toString();

        $statusValue =
            $request->string('status')
                ->trim()
                ->toString();

        $status =
            DocumentStatus::tryFrom(
                $statusValue
            );

        $documents =
            Document::query()
                ->with([
                    'owner',
                    'destination',
                    'approver',
                    'signer',
                ])
                ->when(
                    $search !== '',
                    function (
                        Builder $query
                    ) use ($search): void {
                        $query->where(
                            function (
                                Builder $scope
                            ) use ($search): void {
                                $scope
                                    ->where(
                                        'title',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'document_number',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereHas(
                                        'owner',
                                        fn (Builder $relation) => $relation->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                    )
                                    ->orWhereHas(
                                        'destination',
                                        fn (Builder $relation) => $relation->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                    )
                                    ->orWhereHas(
                                        'approver',
                                        fn (Builder $relation) => $relation->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                    )
                                    ->orWhereHas(
                                        'signer',
                                        fn (Builder $relation) => $relation->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                    );
                            }
                        );
                    }
                )
                ->when(
                    $status !== null,
                    fn (Builder $query) => $query->where(
                        'status',
                        $status->value
                    )
                )
                ->latest('updated_at')
                ->paginate(20)
                ->withQueryString();

        return view(
            'admin.documents.index',
            [
                'documents' => $documents,
                'statuses' => DocumentStatus::cases(),
                'search' => $search,
                'selectedStatus' => $status?->value ?? '',
            ]
        );
    }
}
