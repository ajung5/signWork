@extends('layouts.app')

@section('title', 'Dashboard - SignWork')

@section('content')
    @php
        $draftEnd = $distribution['draft'];
        $submittedEnd = $draftEnd + $distribution['submitted'];
        $waitingEnd = $submittedEnd + $distribution['waiting'];
        $processedEnd = min(100, $waitingEnd + $distribution['processed']);

        $donutStyle =
            $totalDocuments > 0
                ? "background: conic-gradient(
                #94a3b8 0% {$draftEnd}%,
                #3b82f6 {$draftEnd}% {$submittedEnd}%,
                #f59e0b {$submittedEnd}% {$waitingEnd}%,
                #10b981 {$waitingEnd}% {$processedEnd}%,
                #e2e8f0 {$processedEnd}% 100%
            );"
                : 'background: #e2e8f0;';
    @endphp

    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium text-blue-700">
                Dashboard Ringkasan
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Selamat Datang, {{ auth()->user()->name }}
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Berikut ringkasan aktivitas workflow dokumen Anda.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('documents.index') }}"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                Lihat Dokumen
            </a>

        </div>
    </div>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('documents.index') }}"
            class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-600">
                        Total Dokumen
                    </p>

                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                        {{ number_format($totalDocuments) }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">
                        Dalam lingkup akses Anda
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M7 3h7l4 4v14H7V3Z" />
                        <path d="M14 3v5h5M10 13h5M10 17h5" />
                    </svg>
                </div>
            </div>
        </a>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-600">
                        Draft
                    </p>

                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                        {{ number_format($draftDocuments) }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">
                        Masih dapat diedit
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 20h4l11-11-4-4L4 16v4Z" />
                        <path d="m13 7 4 4" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-600">
                        Diajukan
                    </p>

                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                        {{ number_format($submittedDocuments) }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">
                        Menunggu penetapan Verifikator
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 12h13" />
                        <path d="m13 7 5 5-5 5" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-600">
                        Menunggu Verifikasi
                    </p>

                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                        {{ number_format($waitingApprovalDocuments) }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">
                        Sedang dalam proses review
                    </p>
                </div>

                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="8" />
                        <path d="M12 8v5l3 2" />
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-3">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h2 class="font-semibold text-slate-900">
                        Aktivitas Workflow
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Distribusi dokumen berdasarkan tahapan utama.
                    </p>
                </div>

                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    {{ number_format($totalDocuments) }} dokumen
                </span>
            </div>

            <div class="space-y-6 p-5 sm:p-6">
                @php
                    $workflowRows = [
                        [
                            'label' => 'Draft',
                            'count' => $draftDocuments,
                            'percentage' => $distribution['draft'],
                            'bar' => 'bg-slate-400'
                        ],
                        [
                            'label' => 'Diajukan',
                            'count' => $submittedDocuments,
                            'percentage' => $distribution['submitted'],
                            'bar' => 'bg-blue-500'
                        ],
                        [
                            'label' => 'Menunggu Verifikasi',
                            'count' => $waitingApprovalDocuments,
                            'percentage' => $distribution['waiting'],
                            'bar' => 'bg-amber-500'
                        ],
                        [
                            'label' => 'Diproses / Selesai',
                            'count' => $processedDocuments,
                            'percentage' => $distribution['processed'],
                            'bar' => 'bg-emerald-500'
                        ]
                    ];
                @endphp

                @foreach ($workflowRows as $row)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-4">
                            <span class="text-sm font-medium text-slate-700">
                                {{ $row['label'] }}
                            </span>

                            <span class="text-sm font-semibold text-slate-900">
                                {{ number_format($row['count']) }}
                            </span>
                        </div>

                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $row['bar'] }}"
                                style="width: {{ max($row['percentage'], $row['count'] > 0 ? 3 : 0) }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="font-semibold text-slate-900">
                    Status Dokumen
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Proporsi status dalam lingkup akses Anda.
                </p>
            </div>

            <div class="flex flex-col items-center gap-7 p-6 sm:flex-row sm:items-center">
                <div class="relative h-40 w-40 shrink-0 rounded-full" style="{{ $donutStyle }}">
                    <div class="absolute inset-5.5 flex flex-col items-center justify-center rounded-full bg-white">
                        <span class="text-2xl font-semibold text-slate-900">
                            {{ number_format($totalDocuments) }}
                        </span>

                        <span class="text-xs text-slate-400">
                            Total
                        </span>
                    </div>
                </div>

                <div class="w-full space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                            Draft
                        </span>

                        <strong class="text-sm text-slate-800">
                            {{ $draftDocuments }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                            Diajukan
                        </span>

                        <strong class="text-sm text-slate-800">
                            {{ $submittedDocuments }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            Menunggu
                        </span>

                        <strong class="text-sm text-slate-800">
                            {{ $waitingApprovalDocuments }}
                        </strong>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            Diproses
                        </span>

                        <strong class="text-sm text-slate-800">
                            {{ $processedDocuments }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div
            class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="font-semibold text-slate-900">
                    Dokumen Terbaru
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Lima dokumen terakhir dalam lingkup akses Anda.
                </p>
            </div>

            <a href="{{ route('documents.index') }}"
                class="inline-flex items-center text-sm font-medium text-blue-700 hover:text-blue-800">
                Lihat Semua →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                            Nomor
                        </th>

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
                            Diperbarui
                        </th>

                        <th
                            class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                            Status
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($latestDocuments as $document)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm sm:px-6">
                                <a href="{{ route('documents.show', $document) }}"
                                    class="font-semibold text-blue-700 hover:text-blue-800">
                                    {{ $document->document_number ?? 'Tanpa nomor' }}
                                </a>
                            </td>

                            <td class="min-w-72 px-5 py-4 sm:px-6">
                                <p class="text-sm font-medium text-slate-800">
                                    {{ $document->title }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600 sm:px-6">
                                {{ $document->owner->name }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500 sm:px-6">
                                {{ $document->updated_at->format('d M Y, H:i') }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                <x-status-badge :status="$document->status" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700">
                                    Belum ada dokumen.
                                </p>

                                <p class="mt-1 text-sm text-slate-500">
                                    Buat dokumen pertama untuk memulai workflow.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
