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
            'pdf' => ['required', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:'.config('signwork.max_upload_kb')],
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
            'approvers' => ['required', 'array', 'min:1', 'max:10'],
            'approvers.*' => [
                'required',
                'integer',
                'distinct',
                $this->masterEntryRule(WorkflowMasterType::Approver),
            ],
            'signers' => ['required', 'array', 'min:1', 'max:10'],
            'signers.*' => [
                'required',
                'integer',
                'distinct',
                $this->masterEntryRule(WorkflowMasterType::Signer),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pdf.required' => 'Dokumen sumber wajib diunggah.',
            'destination_user_id.exists' => 'Tujuan dokumen harus berasal dari Data Master Tujuan Anda.',
            'approvers.*.distinct' => 'Verifikator tidak boleh dipilih berulang.',
            'signers.*.distinct' => 'Signer tidak boleh dipilih berulang.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'approvers' => $this->participantInput('approvers', 'approver_id'),
            'signers' => $this->participantInput('signers', 'signer_id'),
        ]);
    }

    /** @return list<mixed> */
    private function participantInput(string $plural, string $legacy): array
    {
        $value = $this->input($plural);

        if (is_array($value)) {
            return array_values($value);
        }

        return filled($this->input($legacy)) ? [$this->input($legacy)] : [];
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
