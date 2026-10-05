@extends('layouts.app')

@section('title', 'Dashboard Admin - SignWork')

@section('content')
    @php
        $draftEnd = $distribution['draft'];
        $submittedEnd = $draftEnd + $distribution['submitted'];
        $waitingEnd = $submittedEnd + $distribution['waiting'];
        $processedEnd = min(100, $waitingEnd + $distribution['processed']);

        $donutStyle =
            $totalDocuments > 0
                ? "background: conic-gradient(
                #b8c5df 0% {$draftEnd}%,
                #76C0EC {$draftEnd}% {$submittedEnd}%,
                #FFF6DC {$submittedEnd}% {$waitingEnd}%,
                #425B9A {$waitingEnd}% {$processedEnd}%,
                #e2e8f0 {$processedEnd}% 100%
            );"
                : 'background: #e2e8f0;';
    @endphp

    <div class="mx-auto max-w-[1600px]">
        <div class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_auto] 2xl:items-center">
            <div>
                <p class="text-sm font-medium text-blue-700">
                    Monitoring Sistem
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Dashboard Admin
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Ringkasan seluruh dokumen SignWork. Admin dapat mereview detail dokumen secara read-only dan mengelola
                    akun User.
                </p>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-2 2xl:w-auto">
                <a href="{{ route('admin.documents.index') }}"
                    class="inline-flex min-h-12 items-center justify-center whitespace-nowrap rounded-lg border border-blue-700 bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800">
                    Lihat Semua Dokumen
                </a>

                <a href="{{ route('admin.users.create') }}"
                    class="inline-flex min-h-12 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-cyan-300 bg-brand-sky px-5 py-3 text-sm font-semibold text-blue-950 transition hover:bg-cyan-200">
                    <span class="text-lg leading-none">+</span>
                    Tambah User
                </a>
            </div>
        </div>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
                <p class="text-sm font-medium text-blue-700">
                    Total Dokumen
                </p>

                <p class="mt-2 text-3xl font-semibold tracking-tight text-blue-950">
                    {{ number_format($totalDocuments) }}
                </p>

                <p class="mt-2 text-xs text-blue-700">
                    Seluruh dokumen pada sistem
                </p>
            </div>

            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-5">
                <p class="text-sm font-medium text-indigo-700">
                    Total Akun
                </p>

                <p class="mt-2 text-3xl font-semibold tracking-tight text-indigo-950">
                    {{ number_format($totalUsers) }}
                </p>

                <p class="mt-2 text-xs text-indigo-700">
                    User dan Admin
                </p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-brand-cream p-5">
                <p class="text-sm font-medium text-emerald-700">
                    User Operasional
                </p>

                <p class="mt-2 text-3xl font-semibold tracking-tight text-emerald-950">
                    {{ number_format($regularUsers) }}
                </p>

                <p class="mt-2 text-xs text-emerald-700">
                    Dapat menjalankan workflow
                </p>
            </div>

            <div class="rounded-xl border border-rose-200 bg-rose-100 p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-600">
                    Akun Admin
                </p>

                <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                    {{ number_format($adminUsers) }}
                </p>

                <p class="mt-2 text-xs text-slate-400">
                    Monitoring dan administrasi
                </p>
            </div>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-5">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-slate-900">
                        Distribusi Workflow
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Visualisasi distribusi status utama seluruh dokumen.
                    </p>
                </div>

                <div class="flex flex-col items-center gap-7 p-6 sm:flex-row sm:justify-center xl:flex-col">
                    <div class="relative flex h-52 w-52 shrink-0 items-center justify-center rounded-full"
                        style="{{ $donutStyle }}" aria-label="Chart distribusi workflow dokumen">
                        <div
                            class="flex h-32 w-32 flex-col items-center justify-center rounded-full border border-slate-100 bg-white shadow-sm">
                            <span class="text-3xl font-semibold tracking-tight text-slate-900">
                                {{ number_format($totalDocuments) }}
                            </span>

                            <span class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                                Dokumen
                            </span>
                        </div>
                    </div>

                    <div class="grid w-full gap-3">
                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#b8c5df]"></span>
                                <span class="text-sm text-slate-600">Draft</span>
                            </div>

                            <span class="text-sm font-semibold text-slate-900">
                                {{ number_format($distribution['draft'], 1) }}%
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-sky"></span>
                                <span class="text-sm text-blue-700">Diajukan</span>
                            </div>

                            <span class="text-sm font-semibold text-blue-900">
                                {{ number_format($distribution['submitted'], 1) }}%
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full border border-amber-300 bg-brand-cream"></span>
                                <span class="text-sm text-amber-700">Menunggu Verifikasi</span>
                            </div>

                            <span class="text-sm font-semibold text-amber-900">
                                {{ number_format($distribution['waiting'], 1) }}%
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-navy"></span>
                                <span class="text-sm text-emerald-700">Tahap Lanjutan</span>
                            </div>

                            <span class="text-sm font-semibold text-emerald-900">
                                {{ number_format($distribution['processed'], 1) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-3">
                <div class="mb-4">
                    <h2 class="font-semibold text-slate-900">
                        Ringkasan Status Dokumen
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Jumlah dokumen untuk setiap tahapan workflow.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($statusSummary as $item)
                        @php
                            [$border, $background, $text] = match ($item['status']) {
                                \App\Enums\DocumentStatus::Draft => [
                                    'border-slate-200',
                                    'bg-slate-50',
                                    'text-slate-800',
                                ],
                                \App\Enums\DocumentStatus::Submitted => ['border-sky-200', 'bg-sky-50', 'text-sky-800'],
                                \App\Enums\DocumentStatus::WaitingApproval => [
                                    'border-amber-200',
                                    'bg-amber-50',
                                    'text-amber-800',
                                ],
                                \App\Enums\DocumentStatus::Approved => [
                                    'border-emerald-200',
                                    'bg-emerald-50',
                                    'text-emerald-800',
                                ],
                                \App\Enums\DocumentStatus::Rejected => ['border-red-200', 'bg-red-50', 'text-red-800'],
                                \App\Enums\DocumentStatus::WaitingSignature => [
                                    'border-violet-200',
                                    'bg-violet-50',
                                    'text-violet-800',
                                ],
                                \App\Enums\DocumentStatus::Signing => [
                                    'border-indigo-200',
                                    'bg-indigo-50',
                                    'text-indigo-800',
                                ],
                                \App\Enums\DocumentStatus::Signed => ['border-teal-200', 'bg-teal-50', 'text-teal-800'],
                                \App\Enums\DocumentStatus::SignFailed => [
                                    'border-rose-200',
                                    'bg-rose-50',
                                    'text-rose-800',
                                ],
                            };
                        @endphp

                        <div class="rounded-xl border {{ $border }} {{ $background }} p-4">
                            <p class="text-sm font-medium {{ $text }}">
                                {{ $item['status']->label() }}
                            </p>

                            <p class="mt-2 text-2xl font-semibold tracking-tight {{ $text }}">
                                {{ number_format($item['count']) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div
                class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="font-semibold text-slate-900">
                        10 Dokumen Terbaru
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Dashboard hanya menampilkan sepuluh dokumen terbaru. Gunakan menu Semua Dokumen untuk monitoring
                        lengkap.
                    </p>
                </div>

                <a href="{{ route('admin.documents.index') }}"
                    class="inline-flex rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-medium text-cyan-800 transition hover:bg-cyan-100">
                    Semua Dokumen
                </a>
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
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Review
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($latestDocuments as $document)
                            <tr class="transition hover:bg-slate-50">
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

                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <a href="{{ route('documents.show', $document) }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center text-sm text-slate-500">
                                    Belum ada dokumen.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
