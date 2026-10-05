@extends('layouts.app')

@section('title', 'Semua Dokumen - SignWork')

@section('content')
    <div class="mx-auto max-w-[1600px]">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-cyan-700">
                    Monitoring Dokumen
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Semua Dokumen
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Review seluruh dokumen SignWork tanpa mengubah workflow. Gunakan pencarian dan filter status untuk
                    mempersempit hasil.
                </p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 shadow-sm">
                Total hasil:
                <span class="font-semibold text-slate-900">
                    {{ number_format($documents->total()) }}
                </span>
            </div>
        </div>

        <section class="mt-7 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <form action="{{ route('admin.documents.index') }}" method="GET"
                class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px_auto]">
                <div>
                    <label for="q" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Cari
                    </label>

                    <input id="q" name="q" type="search" value="{{ $search }}"
                        placeholder="Judul, nomor, pemilik, tujuan, Verifikator, Signer..."
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-cyan-400 focus:bg-white focus:ring-4 focus:ring-cyan-100">
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Status
                    </label>

                    <select id="status" name="status"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-cyan-400 focus:bg-white focus:ring-4 focus:ring-cyan-100">
                        <option value="">
                            Semua Status
                        </option>

                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100">
                        Terapkan
                    </button>

                    @if ($search !== '' || $selectedStatus !== '')
                        <a href="{{ route('admin.documents.index') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="font-semibold text-slate-900">
                    Daftar Dokumen
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Admin hanya dapat melakukan review read-only.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Dokumen
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Pemilik
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Tujuan
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Verifikator
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Signer
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Status
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Update
                            </th>

                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Review
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <tr class="transition hover:bg-cyan-50/30">
                                <td class="min-w-72 px-5 py-4 sm:px-6">
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ $document->title }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $document->document_number ?? 'Tanpa nomor' }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700 sm:px-6">
                                    {{ $document->owner->name }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700 sm:px-6">
                                    {{ $document->destination?->name ?? '-' }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700 sm:px-6">
                                    {{ $document->approver?->name ?? '-' }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700 sm:px-6">
                                    {{ $document->signer?->name ?? '-' }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                    <x-status-badge :status="$document->status" />
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500 sm:px-6">
                                    {{ $document->updated_at->format('d M Y, H:i') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <a href="{{ route('documents.show', $document) }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center text-sm text-slate-500">
                                    Tidak ada dokumen yang cocok dengan filter.
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
