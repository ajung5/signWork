<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'required',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 2000 karakter.',
        ];
    }
}
