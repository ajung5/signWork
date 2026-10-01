<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin()
            === true;
    }

    public function rules(): array
    {
        return [
            'jabatan' => ['nullable', 'string', 'max:255'],
            'unit_kerja' => ['nullable', 'string', 'max:255'],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'golongan' => ['nullable', 'string', 'max:30'],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('users', 'nik'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'role' => [
                'nullable',
                Rule::in($this->user()?->isSuperadmin() ? array_map(fn (UserRole $role): string => $role->value, UserRole::cases()) : [UserRole::User->value]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email user wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'nik.digits' => 'NIK harus terdiri dari 16 digit.',
            'nik.unique' => 'NIK sudah digunakan oleh user lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
            'role.in' => 'Role akun tidak dapat diberikan oleh pengguna saat ini.',
        ];
    }
}
