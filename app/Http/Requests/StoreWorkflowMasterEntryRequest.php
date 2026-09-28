<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowMasterEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
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
                )->where(
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

    public function messages(): array
    {
        return [
            'type.required' => 'Jenis Data Master wajib dipilih.',
            'target_user_id.required' => 'User tujuan wajib dipilih.',
            'target_user_id.exists' => 'User yang dipilih tidak valid.',
            'target_user_id.unique' => 'User tersebut sudah terdaftar pada jenis Data Master yang sama.',
        ];
    }
}
