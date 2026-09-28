@extends('layouts.app')
@section('title', 'Tinjau posisi Spesiment - SignWork')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('documents.show', $document) }}" class="text-sm text-blue-700">← Kembali ke dokumen</a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Mode tinjau</p>
            <h1 class="mt-1 text-2xl font-semibold">Tinjau posisi Spesiment</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $document->title }}</p>
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
        <p class="font-semibold">Preview read-only</p>
        <p class="mt-1">Halaman ini hanya menampilkan posisi Spesiment yang sudah tersimpan. Gunakan tombol <span class="font-semibold">Edit posisi Spesiment</span> jika perlu mengubah peserta, cakupan halaman, format, atau koordinat.</p>
    </div>

    <section
        class="space-y-5 rounded-xl border border-slate-200 bg-white p-5"
        data-specimen-review
        data-preview-url="{{ route('documents.pdf.preview', $document) }}"
    >
        <script type="application/json" data-specimen-review-data>@json(['pages' => $pages, 'steps' => $specimenSteps], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-3">
            <label class="text-sm font-medium">
                Tampilkan signer
                <select data-review-signer class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5">
                    <option value="all">Semua signer</option>
                    @foreach($specimenSteps as $step)
                        <option value="{{ $loop->index }}">Signer {{ $loop->iteration }} — {{ $step['name_snapshot'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">
                Halaman preview
                <select data-review-page class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5"></select>
            </label>
            <div class="flex items-end">
                <p data-review-status class="w-full rounded-lg bg-white p-2.5 text-sm text-slate-600" role="status"></p>
            </div>
        </div>

        <div data-review-scroll class="overflow-auto rounded-lg border border-slate-300 bg-slate-100 p-3">
            <div data-review-surface class="relative mx-auto bg-white" style="width:760px">
                <img data-review-page-image alt="Preview halaman dokumen" draggable="false" class="block h-auto w-full select-none">
                <div data-review-blocks class="pointer-events-none absolute inset-0"></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs text-slate-600">
            @foreach($specimenSteps as $step)
                <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-rose-800">Signer {{ $loop->iteration }}: {{ $step['name_snapshot'] }}</span>
            @endforeach
        </div>
    </section>
</div>
@endsection
