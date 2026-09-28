<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class ConfigureDocumentWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && $this->user()?->can('update', $document) === true;
    }

    public function rules(): array
    {
        return [
            'pdf' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'extensions:pdf,doc,docx', 'max:'.config('signwork.max_upload_kb')],
            'approvers' => ['required', 'array', 'min:1', 'max:10'],
            'approvers.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'signers' => ['required', 'array', 'min:1', 'max:10'],
            'signers.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }
}
