<?php

namespace App\Http\Requests;

use App\Enums\WorkflowMasterType;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document =
            $this->route('document');

        return $document
            instanceof Document
            && $this->user()?->can(
                'assignSigner',
                $document
            ) === true;
    }

    public function rules(): array
    {
        /** @var Document $document */
        $document =
            $this->route('document');

        return [
            'signer_id' => [
                'required',
                'integer',
                Rule::exists(
                    'workflow_master_entries',
                    'target_user_id'
                )->where(
                    fn ($query) => $query
                        ->where(
                            'user_id',
                            $document->owner_id
                        )
                        ->where(
                            'type',
                            WorkflowMasterType::Signer->value
                        )
                ),
            ],
        ];
    }
}
