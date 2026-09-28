<?php

namespace App\Http\Requests;

use App\Enums\WorkflowMasterType;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document =
            $this->route('document');

        return $document
            instanceof Document
            && $this->user()?->can(
                'update',
                $document
            ) === true;
    }

    public function rules(): array
    {
        /** @var Document $document */
        $document =
            $this->route('document');

        return [
            'document_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(
                    'documents',
                    'document_number'
                )->ignore(
                    $document->id
                ),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'destination_user_id' => [
                'required',
                'integer',
                $this->masterOrCurrentRule(
                    WorkflowMasterType::Destination,
                    $document->destination_user_id
                ),
            ],
            'approver_id' => [
                'required',
                'integer',
                $this->masterOrCurrentRule(
                    WorkflowMasterType::Approver,
                    $document->approver_id
                ),
            ],
            'signer_id' => [
                'required',
                'integer',
                $this->masterOrCurrentRule(
                    WorkflowMasterType::Signer,
                    $document->signer_id
                ),
            ],
        ];
    }

    private function masterOrCurrentRule(
        WorkflowMasterType $type,
        ?int $currentUserId
    ): \Closure {
        return function (
            string $attribute,
            mixed $value,
            \Closure $fail
        ) use (
            $type,
            $currentUserId
        ): void {
            $targetUserId = (int) $value;

            if (
                $currentUserId !== null
                && $targetUserId
                    === $currentUserId
            ) {
                return;
            }

            if ($targetUserId === $this->user()->id) {
                return;
            }

            $exists = DB::table(
                'workflow_master_entries'
            )
                ->where(
                    'user_id',
                    $this->user()->id
                )
                ->where(
                    'type',
                    $type->value
                )
                ->where(
                    'target_user_id',
                    $targetUserId
                )
                ->exists();

            if (! $exists) {
                $fail(
                    'Pilihan harus berasal dari Data Master akun Anda atau akun Anda sendiri.'
                );
            }
        };
    }
}
