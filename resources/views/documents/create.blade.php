@extends('layouts.app')

@section('title', 'Buat Dokumen - SignWork')

@section('content')
    @php
        $masterReady =
            $destinationUsers->isNotEmpty()
            && $approverUsers->isNotEmpty()
            && $signerUsers->isNotEmpty();
    @endphp

    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-blue-700">
                    Dokumen Baru
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Buat Dokumen
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Pilihan Tujuan, Verifikator, dan Signer berasal dari Data Master akun Anda.
                    Pilih peserta pertama di sini. Setelah Draft disimpan, unggah PDF dan tambahkan peserta berikutnya melalui PDF & Alur.
                </p>
            </div>

            <a
                href="{{ route('workflow-settings.edit') }}"
                class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800 hover:bg-emerald-100"
            >
                Kelola Data Master
            </a>
        </div>

        @if (! $masterReady)
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <p class="text-sm font-semibold text-amber-900">
                    Data Master belum lengkap
                </p>

                <p class="mt-1 text-sm leading-6 text-amber-800">
                    Tambahkan minimal satu Tujuan Dokumen, satu Verifikator, dan satu Signer sebelum menyimpan dokumen baru.
                </p>
            </div>
        @endif

        <form
            action="{{ route('documents.store') }}"
            method="POST"
            class="mt-7 grid gap-6 lg:grid-cols-3"
        >
            @csrf

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-slate-900">
                        Informasi Dokumen
                    </h2>
                </div>

                <div class="space-y-6 p-5 sm:p-6">
                    <div>
                        <label
                            for="document_number"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Nomor Dokumen
                        </label>

                        <input
                            id="document_number"
                            name="document_number"
                            type="text"
                            value="{{ old('document_number') }}"
                            placeholder="Contoh: 001/SF/IX/2026"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >

                        @error('document_number')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="title"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Judul Dokumen
                        </label>

                        <input
                            id="title"
                            name="title"
                            type="text"
                            value="{{ old('title') }}"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >

                        @error('title')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="description"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Deskripsi
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="7"
                            class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900 outline-none focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >{{ old('description') }}</textarea>

                        @error('description')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm">
                    <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">
                            Alur Dokumen
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Sumber pilihan: Data Master Anda.
                        </p>
                    </div>

                    <div class="space-y-5 p-5">
                        <div>
                            <label
                                for="destination_user_id"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Tujuan Dokumen
                            </label>

                            <select
                                id="destination_user_id"
                                name="destination_user_id"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800"
                            >
                                <option value="">
                                    Pilih tujuan
                                </option>

                                @foreach ($destinationUsers as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        @selected(old('destination_user_id', $defaults['destination_user_id']) == $user->id)
                                    >
                                        {{ $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>

                            @error('destination_user_id')
                                <p class="mt-2 text-sm text-red-700">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="approver_id"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Verifikator
                            </label>

                            <select
                                id="approver_id"
                                name="approver_id"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800"
                            >
                                <option value="">
                                    Pilih Verifikator
                                </option>

                                @foreach ($approverUsers as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        @selected(old('approver_id', $defaults['approver_id']) == $user->id)
                                    >
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            (Saya)
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @error('approver_id')
                                <p class="mt-2 text-sm text-red-700">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="signer_id"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Signer
                            </label>

                            <select
                                id="signer_id"
                                name="signer_id"
                                required
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800"
                            >
                                <option value="">
                                    Pilih Signer
                                </option>

                                @foreach ($signerUsers as $user)
                                    <option
                                        value="{{ $user->id }}"
                                        @selected(old('signer_id', $defaults['signer_id']) == $user->id)
                                    >
                                        {{ $user->name }}
                                        @if ($user->id === auth()->id())
                                            (Saya)
                                        @endif
                                    </option>
                                @endforeach
                            </select>

                            @error('signer_id')
                                <p class="mt-2 text-sm text-red-700">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <p class="text-sm font-semibold text-blue-900">
                        Assignment per Dokumen
                    </p>

                    <p class="mt-2 text-sm leading-6 text-blue-800">
                        Menghapus Data Master di kemudian hari tidak mengubah assignment dokumen yang sudah tersimpan.
                    </p>
                </div>
            </aside>

            <div class="flex flex-col-reverse gap-3 lg:col-span-3 sm:flex-row sm:justify-end">
                <a
                    href="{{ route('documents.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    @disabled(! $masterReady)
                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-5 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                >
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>
@endsection
