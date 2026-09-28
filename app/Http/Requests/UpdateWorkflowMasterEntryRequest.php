<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowMasterEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry =
            $this->route(
                'workflowMasterEntry'
            );

        return $entry
            instanceof WorkflowMasterEntry
            && $this->user() !== null
            && ! $this->user()->isAdmin()
            && $entry->user_id
                === $this->user()->id;
    }

    public function rules(): array
    {
        /** @var WorkflowMasterEntry $entry */
        $entry =
            $this->route(
                'workflowMasterEntry'
            );

        return [
            'type' => [
                'required',
                Rule::enum(
                    WorkflowMasterType::class
                ),
            ],
            'target_user_id' => [
                'required',
                'integer',
                Rule::exists(
                    'users',
                    'id'
                )->where(
                    fn ($query) => $query->where(
                        'role',
                        UserRole::User->value
                    )
                ),
                Rule::unique(
                    'workflow_master_entries',
                    'target_user_id'
                )
                    ->ignore($entry->id)
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'user_id',
                                $this->user()->id
                            )
                            ->where(
                                'type',
                                $this->string(
                                    'type'
                                )->toString()
                            )
                    ),
            ],
            'is_default' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
