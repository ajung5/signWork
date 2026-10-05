@extends('layouts.app')

@section('title', 'Verifikasi - SignWork')

@section('content')
    <div class="mx-auto max-w-[1500px]">
        <div>
            <p class="text-sm font-medium text-amber-700">
                Inbox Verifikasi
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Verifikasi
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Hanya dokumen berstatus Menunggu Verifikasi yang ditampilkan.
                Setelah Anda memilih Setuju atau Tolak, dokumen otomatis keluar dari inbox ini dan keputusan tidak dapat
                dijalankan ulang.
            </p>
        </div>

        <section class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
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
                            <tr class="transition hover:bg-amber-50/40">
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
                                        class="inline-flex rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-14 text-center">
                                    <p class="text-sm font-semibold text-slate-700">
                                        Tidak ada dokumen yang menunggu verifikasi Anda.
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Dokumen yang sudah diverifikasi atau ditolak tidak ditampilkan kembali.
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
