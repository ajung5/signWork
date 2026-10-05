@extends('layouts.app')

@section('title', 'Tanda Tangan - SignWork')

@section('content')
    <div class="mx-auto max-w-[1500px]">
        <div>
            <p class="text-sm font-medium text-violet-700">
                Signature Inbox
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Tanda Tangan
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Hanya dokumen berstatus Menunggu Tanda Tangan yang ditampilkan.
                Dokumen yang sudah Ditandatangani otomatis keluar dari inbox dan workflow-nya dikunci.
            </p>
        </div>

        <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
            <p class="text-sm leading-6 text-blue-800">
                E-Sign Service belum diaktifkan pada milestone ini. State lock untuk WaitingSignature, Signing, dan Signed
                sudah disiapkan.
            </p>
        </div>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Dokumen
                            </th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Pemilik
                            </th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Tujuan
                            </th>
                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <tr class="transition hover:bg-violet-50/40">
                                <td class="px-6 py-4">
                                    <a href="{{ route('documents.show', $document) }}"
                                        class="text-sm font-semibold text-slate-900 hover:text-blue-700">
                                        {{ $document->title }}
                                    </a>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $document->document_number ?? 'Tanpa nomor' }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-700">
                                    {{ $document->owner->name }}
                                </td>

                                <td class="px-6 py-4 text-sm text-slate-700">
                                    {{ $document->destination?->name ?? '-' }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('documents.show', $document) }}"
                                        class="inline-flex rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100">
                                        Buka
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-14 text-center">
                                    <p class="text-sm font-semibold text-slate-700">
                                        Tidak ada dokumen yang menunggu tanda tangan Anda.
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Dokumen Signed tidak ditampilkan kembali di sini.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($documents->hasPages())
            <div class="mt-6">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
@endsection
