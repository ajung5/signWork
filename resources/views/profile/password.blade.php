@extends('layouts.app')

@section('title', 'Ganti Password - SignWork')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <p class="text-sm font-medium text-emerald-700">Keamanan akun</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Ganti Password</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Perbarui password akun Anda secara berkala dan jangan gunakan
                password yang sama pada layanan lain.</p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                {{ session('success') }}</div>
        @endif

        <form action="{{ route('profile.password.update') }}" method="POST"
            class="space-y-5 rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            <label for="current_password" class="block text-sm font-medium text-slate-700">
                Password saat ini
                <input id="current_password" name="current_password" type="password" required
                    autocomplete="current-password"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm">
                @error('current_password')
                    <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                @enderror
            </label>

            <label for="password" class="block text-sm font-medium text-slate-700">
                Password baru
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm">
                <span class="mt-1 block text-xs text-slate-500">Minimal 8 karakter.</span>
                @error('password')
                    <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                @enderror
            </label>

            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">
                Konfirmasi password baru
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                    autocomplete="new-password"
                    class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm">
                @error('password_confirmation')
                    <span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>
                @enderror
            </label>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit"
                    class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">Simpan
                    Password</button>
                <a href="{{ route('profile.show') }}"
                    class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">Kembali
                    ke Profil</a>
            </div>
        </form>
    </div>
@endsection
