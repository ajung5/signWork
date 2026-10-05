@extends('layouts.app')

@section('title', 'Validasi Dokumen - SignWork')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Validasi TTE</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Validasi Dokumen</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Pindai QR pada dokumen yang sudah ditandatangani atau tempel tautan QR/ID validasinya di bawah.
            </p>
        </div>

        <form method="POST" action="{{ route('validation.lookup') }}"
            class="space-y-4 rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
            @csrf
            <label class="block text-sm font-semibold text-slate-800" for="validation-reference">
                Tautan QR atau ID validasi
            </label>
            <input id="validation-reference" name="reference" value="{{ old('reference') }}" required maxlength="500"
                autocomplete="off" placeholder="https://.../verify/{id} atau UUID dokumen"
                class="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            @error('reference')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <button class="rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800">
                Validasi Dokumen
            </button>
        </form>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
            <p class="font-semibold text-slate-800">Catatan</p>
            <p class="mt-1">Halaman hasil validasi akan menampilkan status dokumen, jumlah tanda tangan, informasi signer,
                dan hash PDF final untuk dibandingkan.</p>
        </div>
    </div>
@endsection
