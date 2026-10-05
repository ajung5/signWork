@extends('layouts.app')

@section('title', $document->title . ' - SignWork')

@section('content')
    @php
        $backRoute = auth()->user()->isAdmin() ? route('admin.documents.index') : route('documents.index');
    @endphp

    <div class="mx-auto max-w-375">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ $backRoute }}" class="font-medium text-slate-500 transition hover:text-blue-700">
                {{ auth()->user()->isAdmin() ? 'Semua Dokumen' : 'Dokumen Saya' }}
            </a>

            <span class="text-slate-300">/</span>

            <span class="text-slate-700">
                {{ $document->document_number ?? 'Tanpa nomor' }}
            </span>
        </div>

        <div class="mt-5 flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        {{ $document->title }}
                    </h1>

                    <x-status-badge :status="$document->status" />
                </div>

                <p class="mt-2 text-sm text-slate-500">
                    Pemilik: {{ $document->owner->name }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (!$document->requires_pdf_workflow || $cycle)
                    @if (!($cycle && $document->isDraft() && !$cycle->positions_confirmed_at))
                        @can('submit', $document)
                            <form action="{{ route('documents.submit', $document) }}" method="POST">
                                @csrf
                                @if ($cycle)
                                    <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                                @endif

                                <button type="submit"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 transition hover:bg-blue-100">
                                    Ajukan Dokumen
                                </button>
                            </form>
                        @endcan
                    @endif
                @endif

                @can('update', $document)
                    @if (!$document->isRejected())
                        <a href="{{ route('documents.edit', $document) }}" @class([
                            'rounded-lg border px-4 py-2.5 text-sm font-semibold transition',
                            'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' => $document->isRejected(),
                            'border-slate-300 bg-white text-slate-700 hover:bg-slate-100' => !$document->isRejected(),
                        ])>
                            Edit
                        </a>
                    @endif
                @endcan
            </div>
        </div>

        @include('documents.workflow-summary')

        @if ($document->isRejected())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-red-900">
                            Dokumen dikembalikan untuk diperbaiki
                        </p>

                        <p class="mt-2 max-w-3xl text-sm leading-6 text-red-800">
                            Verifikator telah menolak dokumen ini. Pembuat dapat langsung membuka halaman perbaikan.
                            Setelah perubahan disimpan, status akan kembali menjadi Draft dan dokumen dapat diajukan ulang.
                        </p>
                    </div>

                </div>

                @if ($document->rejection_reason)
                    <div class="mt-4 rounded-lg border border-red-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-600">
                            Alasan Penolakan
                        </p>

                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">
                            {{ $document->rejection_reason }}
                        </p>
                    </div>
                @endif
            </div>
        @endif

        @if ($document->isSigned())
            <div class="mt-6 rounded-xl border border-teal-200 bg-teal-50 p-5">
                <p class="text-sm font-semibold text-teal-900">
                    Dokumen telah ditandatangani
                </p>

                <p class="mt-2 text-sm leading-6 text-teal-800">
                    Workflow dokumen telah selesai. Opsi verifikasi dan tanda tangan dikunci dan tidak ditampilkan kembali.
                </p>

                @if ($document->signed_at)
                    <p class="mt-2 text-xs text-teal-700">
                        Selesai pada {{ $document->signed_at->format('d M Y, H:i') }}.
                    </p>
                @endif
            </div>
        @elseif ($document->isSigning())
            <div class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                <p class="text-sm font-semibold text-indigo-900">
                    Tanda tangan sedang diproses
                </p>

                <p class="mt-2 text-sm leading-6 text-indigo-800">
                    Dokumen dikunci dari aksi workflow lain selama proses tanda tangan berlangsung.
                </p>
            </div>
        @elseif ($document->isSignFailed())
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-5">
                <p class="text-sm font-semibold text-rose-900">
                    Proses tanda tangan gagal
                </p>

                <p class="mt-2 text-sm leading-6 text-rose-800">
                    Tidak ada aksi verifikasi yang dapat dilakukan pada status ini. Periksa pesan kegagalan sebelum mencoba
                    kembali.
                </p>
            </div>
        @endif

        <div class="mt-7 grid gap-6 xl:grid-cols-3">
            <section class="space-y-6 xl:col-span-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h2 class="font-semibold text-slate-900">Informasi Dokumen</h2>
                        @if ($document->isRejected())
                            @can('update', $document)
                                <a href="{{ route('documents.edit', $document) }}" data-repair-action
                                    class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                                    Perbaiki Dokumen
                                </a>
                            @endcan
                        @endif
                    </div>

                    <div class="p-5 sm:p-6">
                        <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Nomor Dokumen
                                </dt>

                                <dd class="mt-2 text-sm font-medium text-slate-900">
                                    {{ $document->document_number ?? '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Pemilik
                                </dt>

                                <dd class="mt-2 text-sm font-medium text-slate-900">
                                    {{ $document->owner->name }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Tujuan
                                </dt>

                                <dd class="mt-2 text-sm font-medium text-slate-900">
                                    {{ $document->destination?->name ?? '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Verifikator
                                </dt>

                                <dd class="mt-2 text-sm font-medium text-slate-900">
                                    {{ $document->approver?->name ?? '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Signer
                                </dt>

                                <dd class="mt-2 text-sm font-medium text-slate-900">
                                    {{ $document->signer?->name ?? '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                    Jumlah Revisi
                                </dt>

                                <dd class="mt-2 text-sm text-slate-700">
                                    {{ $document->revision_count }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-7 border-t border-slate-100 pt-6">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Deskripsi
                            </p>

                            <p class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700">
                                {{ $document->description ?? 'Tidak ada deskripsi.' }}
                            </p>
                        </div>
                    </div>
                </div>

                @can('assignApprover', $document)
                    <div class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-semibold text-slate-900">
                            Tetapkan Verifikator
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Opsi ini hanya muncul untuk dokumen lama berstatus Diajukan yang belum mempunyai Verifikator aktif.
                        </p>

                        <form action="{{ route('documents.assign-approver', $document) }}" method="POST"
                            class="mt-5 flex flex-col gap-3 sm:flex-row">
                            @csrf
                            @if ($cycle)
                                <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                            @endif

                            <select name="approver_id" required
                                class="block flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                                <option value="">Pilih Verifikator</option>

                                @foreach ($approverUsers as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>

                            <button type="submit"
                                class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-100">
                                Tetapkan
                            </button>
                        </form>
                    </div>
                @endcan

                @if ($document->isWaitingApproval())
                    @can('approve', $document)
                        <div id="workflow-action"
                            class="scroll-mt-6 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4 sm:px-6">
                                <h2 class="font-semibold text-slate-900">
                                    Keputusan Verifikasi
                                </h2>

                                <p class="mt-1 text-sm text-slate-600">
                                    Pilihan ini hanya aktif selama status Menunggu Verifikasi.
                                </p>
                            </div>

                            <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                                    <h3 class="font-semibold text-emerald-900">
                                        Verifikasi
                                    </h3>

                                    <p class="mt-2 text-sm leading-6 text-emerald-800">
                                        Setelah diverifikasi, opsi Verifikasi/Tolak langsung hilang dan dokumen diteruskan ke
                                        tahap berikutnya.
                                    </p>

                                    <form action="{{ route('documents.approve', $document) }}" method="POST" class="mt-4">
                                        @csrf
                                        @if ($cycle)
                                            <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                                        @endif

                                        <button type="submit"
                                            class="rounded-lg border border-emerald-300 bg-emerald-100 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-200">
                                            Verifikasi Dokumen
                                        </button>
                                    </form>
                                </div>

                                <div class="rounded-xl border border-red-200 bg-red-50 p-5">
                                    <h3 class="font-semibold text-red-900">
                                        Tolak
                                    </h3>

                                    <form action="{{ route('documents.reject', $document) }}" method="POST" class="mt-4">
                                        @csrf
                                        @if ($cycle)
                                            <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                                        @endif

                                        <textarea name="rejection_reason" rows="4" required placeholder="Alasan penolakan..."
                                            class="block w-full rounded-lg border border-red-200 bg-white px-3 py-2.5 text-sm text-slate-900">{{ old('rejection_reason') }}</textarea>

                                        @error('rejection_reason')
                                            <p class="mt-2 text-sm text-red-700">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                        <button type="submit"
                                            class="mt-3 rounded-lg border border-red-300 bg-red-100 px-4 py-2.5 text-sm font-semibold text-red-800 hover:bg-red-200">
                                            Tolak dan Kembalikan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endcan
                @endif

                @can('assignSigner', $document)
                    <div class="rounded-xl border border-violet-200 bg-white p-5 shadow-sm sm:p-6">
                        <h2 class="font-semibold text-slate-900">
                            Tetapkan Signer
                        </h2>

                        <form action="{{ route('documents.assign-signer', $document) }}" method="POST"
                            class="mt-5 flex flex-col gap-3 sm:flex-row">
                            @csrf
                            @if ($cycle)
                                <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                            @endif

                            <select name="signer_id" required
                                class="block flex-1 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                                <option value="">Pilih Signer</option>

                                @foreach ($signerUsers as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} — {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>

                            <button type="submit"
                                class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-800 hover:bg-violet-100">
                                Tetapkan
                            </button>
                        </form>
                    </div>
                @endcan

                @if ($document->isWaitingSignature())
                    <div class="rounded-xl border border-violet-200 bg-violet-50 p-5">
                        <p class="text-sm font-semibold text-violet-900">
                            Menunggu Tanda Tangan
                        </p>

                        <p class="mt-2 text-sm leading-6 text-violet-800">
                            Dokumen menunggu aksi dari {{ $document->signer?->name ?? 'Signer' }}.
                            Setelah status menjadi Ditandatangani, dokumen otomatis tidak lagi muncul di Signature Inbox.
                        </p>
                    </div>
                @endif
            </section>

            <aside class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Status
                    </p>

                    <div class="mt-3">
                        <x-status-badge :status="$document->status" />
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Assignment
                    </p>

                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <p class="text-slate-400">Tujuan</p>
                            <p class="mt-1 font-medium text-slate-800">
                                {{ $document->destination?->name ?? '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-slate-400">Verifikator</p>
                            <p class="mt-1 font-medium text-slate-800">
                                {{ $document->approver?->name ?? '-' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-slate-400">Signer</p>
                            <p class="mt-1 font-medium text-slate-800">
                                {{ $document->signer?->name ?? '-' }}
                            </p>
                        </div>
                    </div>
                </div>

            </aside>
        </div>
    </div>
@endsection
