<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('users', 'nik')->ignore($this->user()?->id),
            ],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'unit_kerja' => ['nullable', 'string', 'max:255'],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'golongan' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama lengkap', 'nik' => 'NIK', 'jabatan' => 'jabatan', 'unit_kerja' => 'unit kerja', 'pangkat' => 'pangkat', 'golongan' => 'golongan'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute maksimal :max karakter.',
            'nik.digits' => 'NIK harus terdiri dari 16 digit.',
            'nik.unique' => 'NIK sudah digunakan oleh user lain.',
        ];
    }
}
