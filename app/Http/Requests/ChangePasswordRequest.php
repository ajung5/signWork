<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'password saat ini',
            'password' => 'password baru',
            'password_confirmation' => 'konfirmasi password baru',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'current_password' => 'Password saat ini tidak sesuai.',
            'confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ];
    }
}
