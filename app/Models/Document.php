<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class Document extends Model {
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'destination_user_id',
        'approver_id',
        'signer_id',
        'document_number',
        'title',
        'description',
        'status',
        'submitted_at',
        'approver_assigned_at',
        'approved_at',
        'signer_assigned_at',
        'rejected_at',
        'rejection_reason',
        'revision_count',
        'last_revised_at',
        'signed_at',
        'requires_pdf_workflow'
    ];

    protected static function booted(): void {
        static::creating(function (Document $document): void {
            $document->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array {
        return [
            'status' => DocumentStatus::class,
            'submitted_at' => 'datetime',
            'approver_assigned_at' => 'datetime',
            'approved_at' => 'datetime',
            'signer_assigned_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_revised_at' => 'datetime',
            'signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'revision_count' => 'integer',
            'workflow_cycle' => 'integer',
            'requires_pdf_workflow' => 'boolean'
        ];
    }

    public function getRouteKeyName(): string {
        return 'uuid';
    }

    public function isDraft(): bool {
        return $this->status === DocumentStatus::Draft;
    }

    public function isSubmitted(): bool {
        return $this->status === DocumentStatus::Submitted;
    }

    public function isWaitingApproval(): bool {
        return $this->status === DocumentStatus::WaitingApproval;
    }

    public function isApproved(): bool {
        return $this->status === DocumentStatus::Approved;
    }

    public function isRejected(): bool {
        return $this->status === DocumentStatus::Rejected;
    }

    public function isWaitingSignature(): bool {
        return $this->status === DocumentStatus::WaitingSignature;
    }

    public function isSigning(): bool {
        return $this->status === DocumentStatus::Signing;
    }

    public function isSigned(): bool {
        return $this->status === DocumentStatus::Signed;
    }

    public function isSignFailed(): bool {
        return $this->status === DocumentStatus::SignFailed;
    }

    public function submit(): void {
        if ($this->requires_pdf_workflow || $this->workflow_cycle > 0) {
            throw new LogicException('PDF workflow must be submitted through DocumentWorkflow.');
        }

        if (!$this->isDraft()) {
            throw new LogicException('Only draft documents can be submitted.');
        }

        $this->submitted_at = now();

        if ($this->approver_id !== null) {
            $this->status = DocumentStatus::WaitingApproval;
            $this->approver_assigned_at = now();
        } else {
            $this->status = DocumentStatus::Submitted;
        }

        $this->save();
    }

    public function assignApprover(User $approver): void {
        if (!$this->isSubmitted()) {
            throw new LogicException('Approver can only be assigned to submitted documents.');
        }

        $this->approver_id = $approver->id;
        $this->approver_assigned_at = now();
        $this->status = DocumentStatus::WaitingApproval;

        $this->save();
    }

    public function approveBy(User $approver): void {
        $this->ensureDecisionCanBeMadeBy($approver);

        $this->approved_at = now();
        $this->rejected_at = null;
        $this->rejection_reason = null;

        if ($this->signer_id !== null) {
            $this->status = DocumentStatus::WaitingSignature;
            $this->signer_assigned_at = now();
        } else {
            $this->status = DocumentStatus::Approved;
        }

        $this->save();
    }

    public function rejectBy(User $approver, string $reason): void {
        $this->ensureDecisionCanBeMadeBy($approver);

        $this->status = DocumentStatus::Rejected;
        $this->approved_at = null;
        $this->signer_assigned_at = null;
        $this->rejected_at = now();
        $this->rejection_reason = trim($reason);

        $this->save();
    }

    public function revise(): void {
        if (!$this->isRejected()) {
            throw new LogicException('Only rejected documents can enter revision.');
        }

        $this->status = DocumentStatus::Draft;
        $this->revision_count = $this->revision_count + 1;
        $this->last_revised_at = now();

        $this->submitted_at = null;
        $this->approver_assigned_at = null;
        $this->approved_at = null;
        $this->signer_assigned_at = null;
        $this->signed_at = null;

        $this->save();
    }

    public function assignSigner(User $signer): void {
        if (!$this->isApproved()) {
            throw new LogicException('Signer can only be assigned to approved documents.');
        }

        $this->signer_id = $signer->id;
        $this->signer_assigned_at = now();
        $this->status = DocumentStatus::WaitingSignature;

        $this->save();
    }

    public function cycles(): HasMany {
        return $this->hasMany(DocumentCycle::class)->orderBy('number');
    }

    public function currentCycle(): ?DocumentCycle {
        return $this->cycles()->where('number', $this->workflow_cycle)->first();
    }

    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function destination(): BelongsTo {
        return $this->belongsTo(User::class, 'destination_user_id');
    }

    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function signer(): BelongsTo {
        return $this->belongsTo(User::class, 'signer_id');
    }

    private function ensureDecisionCanBeMadeBy(User $approver): void {
        if (!$this->isWaitingApproval()) {
            throw new LogicException('Approval decision can only be made while waiting for approval.');
        }

        if ($this->approver_id !== $approver->id) {
            throw new LogicException('Approval decision must be made by the assigned approver.');
        }
    }
}
