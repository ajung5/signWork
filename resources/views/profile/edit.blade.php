@extends('layouts.app')
@section('title', 'Profil Saya - SignWork')
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Profil Saya</h1>
        <p class="mt-2 text-sm text-slate-600">Kelola nama dan identitas yang digunakan pada spesimen tanda tangan Anda.</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Gunakan data pegawai yang benar. Jika profil diisi melalui seeder, ganti data contoh sebelum digunakan untuk dokumen nyata.
        Identitas pada posisi yang sudah dikonfirmasi tetap menggunakan data saat konfirmasi. Untuk memakai profil terbaru pada dokumen draft, muat ulang preview dan konfirmasi ulang posisi.
    </div>
    <form action="{{ route('profile.update') }}" method="POST" class="space-y-6 rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div class="rounded-xl bg-slate-50 p-4 text-sm">
            <p class="text-slate-500">Email akun</p>
            <p class="mt-1 break-all font-medium">{{ $user->email }}</p>
            <p class="mt-2 text-xs text-slate-500">Hubungi admin untuk perubahan email akun.</p>
        </div>
        <label for="name" class="block text-sm font-medium text-slate-700">Nama lengkap beserta gelar
            <input id="name" name="name" type="text" required maxlength="255" autocomplete="name" value="{{ old('name', $user->name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm">
            @error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
        </label>
        @include('admin.users.specimen-fields', ['user' => $user])
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800">Simpan Profil</button>
            <a href="{{ route('profile.show') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">Lihat Profil</a>
            <a href="{{ route('dashboard') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50">Kembali</a>
        </div>
    </form>
</div>
@endsection
