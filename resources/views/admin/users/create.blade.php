@extends('layouts.app')

@section('title', 'Tambah User - SignWork')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div>
            <p class="text-sm font-medium text-indigo-700">
                Manajemen User
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Tambah User
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Akun baru otomatis dibuat sebagai User operasional dan dapat membuat dokumen, dipilih sebagai tujuan, Verifikator, maupun Signer.
            </p>
        </div>

        <form
            action="{{ route('admin.users.store') }}"
            method="POST"
            class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            @csrf

            <div class="space-y-6 p-5 sm:p-6">
                <div>
                    <label
                        for="name"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Nama lengkap beserta gelar
                    </label>

                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        autocomplete="name"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    >

                    @error('name')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                @if (auth()->user()->isSuperadmin())
                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700">Role akun</label>
                        <select id="role" name="role" class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                            @foreach (\App\Enums\UserRole::cases() as $role)
                                <option value="{{ $role->value }}" @selected(old('role', 'user') === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endif

                @include('admin.users.specimen-fields')

                <div>
                    <label
                        for="email"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    >

                    @error('email')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="password"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Password
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    >

                    @error('password')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="password_confirmation"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Konfirmasi Password
                    </label>

                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-indigo-400 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    >
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="text-sm leading-6 text-blue-800">
                        Admin dapat membuat akun User. Superadmin dapat membuat akun User, Admin, atau Superadmin.
                    </p>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100"
                >
                    Tambah User
                </button>
            </div>
        </form>
    </div>
@endsection
