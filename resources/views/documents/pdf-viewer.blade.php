@extends('layouts.app')
@section('title', 'Preview dokumen - SignWork')
@section('content')
    <div class="mx-auto max-w-6xl space-y-4">
        <a href="{{ route('documents.show', $document) }}" aria-label="Kembali ke detail dokumen"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                <path d="M19 12H5" />
                <path d="m11 18-6-6 6-6" />
            </svg>
            Kembali ke dokumen
        </a>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $document->title }}</h1>
                <p class="text-sm text-slate-600">Preview {{ $version }} · {{ $cycle->source_name ?? 'PDF' }}</p>
            </div>
            <a href="{{ route('documents.pdf.download', [$document, 'version' => $version]) }}"
                class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Unduh PDF ini</a>
        </div>
        @if ($cycle->source_name && !str_ends_with(strtolower($cycle->source_name), '.pdf'))
            <p class="rounded-lg bg-blue-50 p-3 text-sm">Ini PDF hasil konversi Word. Periksa font, tabel, dan pemisahan
                halaman sebelum melanjutkan.</p>
        @endif
        <iframe src="{{ route('documents.pdf.download', [$document, 'version' => $version, 'inline' => 1]) }}"
            title="Preview PDF {{ $document->title }}" class="w-full rounded-lg border border-slate-300 bg-white"
            style="height:75vh"></iframe>
        <p class="text-sm text-slate-600">Jika browser tidak mendukung preview PDF, <a class="text-blue-700 underline"
                target="_blank" rel="noopener"
                href="{{ route('documents.pdf.download', [$document, 'version' => $version, 'inline' => 1]) }}">buka preview
                di tab baru</a>.</p>
    </div>
@endsection
