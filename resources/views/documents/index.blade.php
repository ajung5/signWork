@extends('layouts.app')

@section('title', 'Dokumen Saya - SignWork')

@section('content')
    <div class="mx-auto max-w-375">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-cyan-700">
                    Pengelolaan Dokumen
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Dokumen Saya
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    Buat dan kelola dokumen Anda, mulai dari draft hingga selesai ditandatangani dan dikirim.
                </p>
            </div>

            <a href="{{ route('documents.create') }}"
                class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 transition hover:bg-blue-100">
                Buat Dokumen
            </a>
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
                                Status
                            </th>

                            <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <tr class="transition hover:bg-slate-50">
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

                                <td class="px-6 py-4">
                                    <x-status-badge :status="$document->status" />
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('documents.show', $document) }}"
                                        class="inline-flex rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-800 hover:bg-cyan-100">
                                        Buka
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-14 text-center text-sm text-slate-500">
                                    Belum ada dokumen. Klik Buat Dokumen untuk memulai.
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
