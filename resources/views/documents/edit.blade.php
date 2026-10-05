@extends('layouts.app')

@section('title', 'Edit Dokumen - SignWork')

@section('content')
    <div class="mx-auto max-w-6xl">
        <div>
            <p class="text-sm font-medium {{ $document->isRejected() ? 'text-amber-700' : 'text-blue-700' }}">
                {{ $document->isRejected() ? 'Perbaikan Dokumen Ditolak' : 'Perbarui Draft' }}
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                {{ $document->isRejected() ? 'Perbaiki Dokumen' : 'Edit Dokumen' }}
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                @if ($document->isRejected())
                    Dokumen dikembalikan oleh Verifikator. Setelah perubahan disimpan, status otomatis kembali menjadi Draft
                    dan dapat diajukan ulang.
                @else
                    Tujuan, Verifikator, dan Signer dapat diubah selama dokumen masih Draft.
                @endif
            </p>
        </div>

        @if ($document->isRejected() && $document->rejection_reason)
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">
                    Catatan Verifikator
                </p>

                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-red-900">
                    {{ $document->rejection_reason }}
                </p>
            </div>
        @endif

        @if ($document->isDraft() && $cycle)
            <section class="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-blue-950">
                            Upload Dokumen Revisi
                        </h2>

                        <p class="mt-1 max-w-3xl text-sm leading-6 text-blue-900">
                            Unggah PDF, DOC, atau DOCX jika isi dokumen sumber perlu diganti. Setelah upload berhasil,
                            posisi spesimen akan dikosongkan dan harus diperiksa serta dikonfirmasi ulang.
                        </p>
                    </div>

                    <form action="{{ route('documents.revision.upload', $document) }}" method="POST"
                        enctype="multipart/form-data" class="flex w-full flex-col gap-3 sm:flex-row sm:items-end lg:w-auto">
                        @csrf

                        <div class="min-w-0 sm:min-w-72">
                            <label for="revision-pdf" class="block text-xs font-semibold text-blue-950">
                                File dokumen revisi
                            </label>

                            <input id="revision-pdf" name="pdf" type="file"
                                accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                required
                                class="mt-1 block w-full rounded-lg border border-blue-300 bg-white px-3 py-2 text-sm text-slate-900">

                            @error('pdf')
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                            class="inline-flex items-center justify-center whitespace-nowrap rounded-lg border border-blue-700 bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                            Upload Dokumen Revisi
                        </button>
                    </form>
                </div>

                <p class="mt-3 text-xs text-blue-800">
                    Maksimal 5 MB. Dokumen Word akan dikonversi menjadi PDF sebelum posisi spesimen diatur ulang.
                </p>
            </section>
        @endif

        <form action="{{ route('documents.update', $document) }}" method="POST" class="mt-7 grid gap-6 lg:grid-cols-3">
            @csrf
            @method('PUT')

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="space-y-6 p-5 sm:p-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Nomor Dokumen
                        </label>

                        <input name="document_number" type="text"
                            value="{{ old('document_number', $document->document_number) }}"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900">

                        @error('document_number')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Judul Dokumen
                        </label>

                        <input name="title" type="text" required value="{{ old('title', $document->title) }}"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900">

                        @error('title')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Deskripsi
                        </label>

                        <textarea name="description" rows="7"
                            class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-900">{{ old('description', $document->description) }}</textarea>
                    </div>
                </div>
            </section>

            <aside class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">
                        Alur Dokumen
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Pilihan baru berasal dari Data Master Anda.
                    </p>
                </div>

                <div class="space-y-5 p-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            Tujuan Dokumen
                        </label>

                        <select name="destination_user_id" required
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                            @foreach ($destinationUsers as $user)
                                <option value="{{ $user->id }}" @selected(old('destination_user_id', $document->destination_user_id) == $user->id)>
                                    {{ $user->name }} — {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($document->workflow_cycle > 0)
                        <input type="hidden" name="approver_id" value="{{ $document->approver_id }}">
                        <input type="hidden" name="signer_id" value="{{ $document->signer_id }}">
                        <p class="text-sm text-slate-600">Urutan verifikator dan signer dikelola di halaman PDF & Alur
                            setelah metadata disimpan.</p>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-slate-700">
                                Verifikator
                            </label>

                            <select name="approver_id" required
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                                @foreach ($approverUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('approver_id', $document->approver_id) == $user->id)>
                                        {{ $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">
                                Signer
                            </label>

                            <select name="signer_id" required
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                                @foreach ($signerUsers as $user)
                                    <option value="{{ $user->id }}" @selected(old('signer_id', $document->signer_id) == $user->id)>
                                        {{ $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </aside>

            <div class="flex flex-col-reverse gap-3 lg:col-span-3 sm:flex-row sm:justify-end">
                <a href="{{ route('documents.show', $document) }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    Batal
                </a>

                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-5 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">
                    {{ $document->isRejected() ? 'Simpan Perbaikan' : 'Simpan Perubahan' }}
                </button>
            </div>
        </form>
    </div>
@endsection
