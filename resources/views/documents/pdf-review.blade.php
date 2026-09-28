@extends('layouts.app')
@section('title', 'Tinjau posisi Spesiment - SignWork')
@section('content')
<div class="mx-auto max-w-6xl space-y-4">
    <a href="{{ route('documents.show', $document) }}" class="text-sm text-blue-700">← Kembali ke dokumen</a>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Preview PDF tahap berjalan</p>
            <h1 class="mt-1 text-xl font-semibold">{{ $document->title }}</h1>
        </div>
        @can('update', $document)
            @if($document->isDraft())
                <a href="{{ route('documents.pdf.edit', $document) }}" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">
                    Edit posisi Spesiment
                </a>
            @endif
        @endcan
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p class="font-semibold">Preview read-only posisi Spesiment</p>
        <p class="mt-1">PDF ini menggunakan renderer yang sama dengan preview tahap berjalan dan menampilkan footer serta posisi Spesiment yang sudah dikonfirmasi. Gunakan tombol <span class="font-semibold">Edit posisi Spesiment</span> jika ingin mengubahnya.</p>
    </div>

    <iframe src="{{ route('documents.pdf.review-file', $document) }}" title="Preview PDF tahap berjalan {{ $document->title }}" class="w-full rounded-lg border border-slate-300 bg-white" style="height:75vh"></iframe>
    <p class="text-sm text-slate-600">Jika browser tidak mendukung preview PDF, <a class="text-blue-700 underline" target="_blank" rel="noopener" href="{{ route('documents.pdf.review-file', $document) }}">buka preview di tab baru</a>.</p>
</div>
@endsection
