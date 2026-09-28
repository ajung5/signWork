<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(
            'viewApprovalInbox',
            Document::class
        );

        $user = $request->user();

        $documents = Document::query()
            ->where(
                'status',
                DocumentStatus::WaitingApproval->value
            )
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where(
                    'approver_id',
                    $user->id
                )
            )
            ->with([
                'owner',
                'destination',
                'approver',
                'signer',
            ])
            ->latest('approver_assigned_at')
            ->paginate(10);

        return view(
            'approvals.index',
            [
                'documents' => $documents,
            ]
        );
    }
}
