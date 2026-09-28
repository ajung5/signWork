<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return ! $user->isAdmin();
    }

    public function view(
        User $user,
        Document $document
    ): bool {
        return $user->isAdmin()
            || $document->owner_id
                === $user->id
            || ($document->destination_user_id === $user->id && $document->sent_at !== null && $document->isSigned())
            || $document->approver_id
                === $user->id
            || $document->signer_id
                === $user->id
            || $document->cycles()->where('number', $document->workflow_cycle)->whereHas('approvals', fn ($query) => $query->where('user_id', $user->id))->exists()
            || $document->cycles()->where('number', $document->workflow_cycle)->whereHas('signatures', fn ($query) => $query->where('user_id', $user->id))->exists();
    }

    public function create(
        User $user
    ): bool {
        return ! $user->isAdmin();
    }

    public function update(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->owner_id
                === $user->id
            && (
                $document->isDraft()
                || $document->isRejected()
            );
    }

    public function delete(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isDraft()
            && ! $document->cycles()->whereNotNull('submitted_at')->exists()
            && $document->owner_id
                === $user->id;
    }

    public function submit(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isDraft()
            && $document->owner_id
                === $user->id;
    }

    public function assignApprover(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isSubmitted()
            && $document->owner_id
                === $user->id;
    }

    public function approve(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isWaitingApproval()
            && $document->approver_id
                === $user->id;
    }

    public function reject(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isWaitingApproval()
            && $document->approver_id
                === $user->id;
    }

    public function revise(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isRejected()
            && $document->owner_id
                === $user->id;
    }

    public function assignSigner(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isApproved()
            && $document->owner_id
                === $user->id;
    }

    public function send(User $user, Document $document): bool
    {
        if ($user->isAdmin() || ! $document->isSigned() || $document->sent_at || ! $document->destination_user_id) {
            return false;
        }
        $lastSigner = $document->currentCycle()?->signatures()->reorder('sequence', 'desc')->first();

        return $document->owner_id === $user->id || ($lastSigner?->status === 'signed' && $lastSigner->user_id === $user->id);
    }

    public function sign(
        User $user,
        Document $document
    ): bool {
        return ! $user->isAdmin()
            && $document->isWaitingSignature()
            && $document->signer_id
                === $user->id;
    }

    public function viewApprovalInbox(
        User $user
    ): bool {
        return ! $user->isAdmin();
    }

    public function viewSignatureInbox(
        User $user
    ): bool {
        return ! $user->isAdmin();
    }

    public function viewIncomingInbox(
        User $user
    ): bool {
        return ! $user->isAdmin();
    }
}
