@extends('layouts.app')
@section('title', 'Preview dokumen - SignWork')
@section('content')
<div class="mx-auto max-w-6xl space-y-4">
    <a href="{{ route('documents.show', $document) }}" class="text-sm text-blue-700">← Kembali ke dokumen</a>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-xl font-semibold">{{ $document->title }}</h1><p class="text-sm text-slate-600">Preview {{ $version }} · {{ $cycle->source_name ?? 'PDF' }}</p></div>
        <a href="{{ route('documents.pdf.download', [$document, 'version' => $version]) }}" class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Unduh PDF ini</a>
    </div>
    @if($cycle->source_name && !str_ends_with(strtolower($cycle->source_name), '.pdf'))<p class="rounded-lg bg-blue-50 p-3 text-sm">Ini PDF hasil konversi Word. Periksa font, tabel, dan pemisahan halaman sebelum melanjutkan.</p>@endif
    <iframe src="{{ route('documents.pdf.download', [$document, 'version' => $version, 'inline' => 1]) }}" title="Preview PDF {{ $document->title }}" class="w-full rounded-lg border border-slate-300 bg-white" style="height:75vh"></iframe>
    <p class="text-sm text-slate-600">Jika browser tidak mendukung preview PDF, <a class="text-blue-700 underline" target="_blank" rel="noopener" href="{{ route('documents.pdf.download', [$document, 'version' => $version, 'inline' => 1]) }}">buka preview di tab baru</a>.</p>
</div>
@endsection
