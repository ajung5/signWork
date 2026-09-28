<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IncomingDocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(
            'viewIncomingInbox',
            Document::class
        );

        $user = $request->user();

        $documents = Document::query()
            ->whereNotNull('destination_user_id')->whereNotNull('sent_at')
            ->where(
                'status',
                '=',
                DocumentStatus::Signed->value
            )
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where(
                    'destination_user_id',
                    $user->id
                )
            )
            ->with([
                'owner',
                'destination',
                'approver',
                'signer',
            ])
            ->latest('sent_at')
            ->paginate(10);

        return view(
            'incoming-documents.index',
            [
                'documents' => $documents,
            ]
        );
    }
}
