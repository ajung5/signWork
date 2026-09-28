<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SignatureController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(
            'viewSignatureInbox',
            Document::class
        );

        $user = $request->user();

        $documents = Document::query()
            ->where(
                'status',
                DocumentStatus::WaitingSignature->value
            )
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where(
                    'signer_id',
                    $user->id
                )
            )
            ->with([
                'owner',
                'destination',
                'approver',
                'signer',
            ])
            ->latest('signer_assigned_at')
            ->paginate(10);

        return view(
            'signatures.index',
            [
                'documents' => $documents,
            ]
        );
    }
}
