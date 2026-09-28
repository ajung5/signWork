@extends('layouts.app')

@section('title', 'Profil Saya - SignWork')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Akun</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Profil Saya</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Informasi profil ini digunakan sebagai identitas pada spesimen TTE dan dapat diperbarui dari menu Edit Profil.
                </p>
            </div>

            <a href="{{ route('profile.edit') }}"
                class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                Edit Profil
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <dl class="divide-y divide-slate-100">
                @foreach ([
                    'Nama' => $user->name,
                    'Email' => $user->email,
                    'Role' => $user->role->label(),
                    'Jabatan' => $user->jabatan,
                    'Unit Kerja' => $user->unit_kerja,
                    'Pangkat / Golongan' => trim(($user->pangkat ?: '-') . ' / ' . ($user->golongan ?: '-')),
                ] as $label => $value)
                    <div class="grid gap-1 px-5 py-4 sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-slate-500">{{ $label }}</dt>
                        <dd class="text-sm text-slate-900 sm:col-span-2">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
@endsection
