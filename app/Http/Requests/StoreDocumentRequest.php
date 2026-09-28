<?php

namespace App\Http\Requests;

use App\Enums\WorkflowMasterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'document_number' => [
                'nullable',
                'string',
                'max:255',
                'unique:documents,document_number',
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
                $this->masterEntryRule(
                    WorkflowMasterType::Destination
                ),
            ],
            'approver_id' => [
                'required',
                'integer',
                $this->masterEntryRule(
                    WorkflowMasterType::Approver
                ),
            ],
            'signer_id' => [
                'required',
                'integer',
                $this->masterEntryRule(
                    WorkflowMasterType::Signer
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_user_id.exists' => 'Tujuan dokumen harus berasal dari Data Master Tujuan Anda.',
            'approver_id.exists' => 'Approver harus berasal dari Data Master Approver Anda.',
            'signer_id.exists' => 'Signer harus berasal dari Data Master Signer Anda.',
        ];
    }

    private function masterEntryRule(
        WorkflowMasterType $type
    ): \Closure {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
            $targetUserId = (int) $value;

            if ($targetUserId === $this->user()->id) {
                return;
            }

            $exists = DB::table('workflow_master_entries')
                ->where('user_id', $this->user()->id)
                ->where('type', $type->value)
                ->where('target_user_id', $targetUserId)
                ->exists();

            if (! $exists) {
                $fail('Pilihan harus berasal dari Data Master akun Anda atau akun Anda sendiri.');
            }
        };
    }
}
