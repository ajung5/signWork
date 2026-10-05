<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && $this->user()?->can('update', $document) === true;
    }

    public function rules(): array
    {
        return [
            'pdf' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'extensions:pdf,doc,docx',
                'max:'.config('signwork.max_upload_kb'),
            ],
        ];
    }
}
