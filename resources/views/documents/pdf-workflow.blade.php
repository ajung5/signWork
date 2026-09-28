@extends('layouts.app')
@section('title', 'PDF & Alur - SignWork')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <a href="{{ route('documents.show', $document) }}"
        aria-label="Kembali ke detail dokumen"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
            class="h-4 w-4">
            <path d="M19 12H5" />
            <path d="m11 18-6-6 6-6" />
        </svg>
        Kembali ke dokumen
    </a>
    <h1 class="text-2xl font-semibold">PDF & Alur · {{ $document->title }}</h1>
    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Mode simulasi. Jangan memasukkan passphrase BSrE. Siapkan ruang kosong untuk QR. Pilih spesimen berbingkai, QR 2 × 2 cm, atau QR 3 × 3 cm. Ukuran dihitung otomatis.</p>
    <form action="{{ route('documents.pdf.store', $document) }}" method="POST" enctype="multipart/form-data" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        <h2 class="text-lg font-semibold">1. Dokumen sumber dan urutan peserta</h2>
        @if ($cycle)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                Dokumen sumber sudah tersimpan: <strong>{{ $cycle->source_name ?: 'PDF tersimpan' }}</strong>. Tidak perlu mengunggah ulang untuk mengatur peserta dan posisi specimen.
            </div>
        @else
            <label class="block text-sm font-medium">Unggah PDF atau Word
                <input type="file" name="pdf" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required class="mt-2 block w-full rounded-lg border border-slate-300 p-3">
            </label>
            <p class="text-sm text-slate-600">Maksimal 5 MB, tanpa batas jumlah halaman. Word (.doc/.docx) dikonversi menjadi PDF; periksa hasil konversinya. Placeholder: <code>${tte:signer:1}</code>, <code>${tte:signer:2}</code>. PDF hasil scan menggunakan posisi manual.</p>
        @endif
        <div class="grid gap-6 md:grid-cols-2">
        @foreach(['approver' => 'Verifikator', 'signer' => 'Signer'] as $type => $label)
            @php
                $selected = old($type.'s', $cycle ? ($type === 'approver' ? $cycle->approvals : $cycle->signatures)->pluck('user_id')->all() : [$type === 'approver' ? $document->approver_id : $document->signer_id]);
            @endphp
            <section data-participant-list class="space-y-3">
                <h3 class="font-semibold">{{ $label }} — berurutan</h3>
                <div data-rows class="space-y-2">
                @foreach($selected as $id)
                    <div data-row class="flex items-center gap-2">
                        <span data-order class="text-sm">{{ $loop->iteration }}</span>
                        <select name="{{ $type }}s[]" required aria-label="{{ $label }}" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2">
                            <option value="">Pilih {{ $label }}</option>
                            @foreach($participants[$type] as $participant)<option value="{{ $participant->id }}" @selected($participant->id == $id)>{{ $participant->name }}</option>@endforeach
                        </select>
                        <button type="button" data-up aria-label="Naik" class="rounded border px-2 py-1">↑</button>
                        <button type="button" data-down aria-label="Turun" class="rounded border px-2 py-1">↓</button>
                        <button type="button" data-remove aria-label="Hapus peserta" class="rounded border px-2 py-1 text-red-700">×</button>
                    </div>
                @endforeach
                </div>
                <button type="button" data-add class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">+ Tambah {{ $label }}</button>
            </section>
        @endforeach
        </div>
        <p class="text-sm text-slate-600">Nama tersedia dari Data Master. Orang yang sama boleh menjadi verifikator sekaligus signer, tetapi tidak boleh berulang dalam satu daftar.</p>
        <button class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white">{{ $cycle ? 'Simpan peserta & urutan' : 'Simpan dokumen & urutan, lalu periksa posisi' }}</button>
    </form>
    @if($cycle && $document->isDraft())
    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6" data-placement
        data-user-id="{{ auth()->id() }}"
        data-preview-url="{{ route('documents.pdf.preview', $document) }}"
        data-pages="{{ json_encode($cycle->pdf_metadata['pages']) }}"
        data-steps="{{ json_encode($specimenSteps) }}">
        <h2 class="text-lg font-semibold">2. Periksa posisi QR pada preview</h2>
        <p class="text-sm text-slate-600">Pilih signer, cakupan halaman, lalu pilih beberapa halaman bila diperlukan. Klik area kosong atau geser blok pada PDF untuk menentukan posisi. Untuk <strong>Semua halaman</strong>, posisi yang dipilih menjadi posisi yang sama pada setiap halaman. Untuk <strong>beberapa halaman</strong>, dropdown preview hanya menampilkan halaman yang dicentang dan setiap halaman wajib diatur secara terpisah.</p>
        <div class="z-10 grid grid-cols-2 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-0 lg:grid-cols-6">
            <label class="text-sm">Signer <select data-active-signer class="mt-1 block w-full min-w-0 rounded border border-slate-300 p-2"></select></label>
            <label class="text-sm">Cakupan QR <select data-scope class="mt-1 block w-full min-w-0 rounded border border-slate-300 p-2"><option value="all_pages">Semua halaman</option><option value="selected_pages">Pilih beberapa halaman</option></select></label>
            <label class="text-sm">Preview halaman terpilih <select data-page class="mt-1 block w-full min-w-0 rounded border border-slate-300 p-2"></select></label>
            <label class="text-sm">Zoom <select data-zoom class="mt-1 block w-full min-w-0 rounded border border-slate-300 p-2"><option value="1">Pas halaman</option><option value="1.25">125%</option><option value="1.5">150%</option><option value="2">200%</option></select></label>
            <label class="text-sm">Format spesimen <select data-format class="mt-1 w-full rounded border border-slate-300 p-2"><option value="framed">1. QR Code dengan Teks</option><option value="qr_2cm">2. QR Code (2 × 2 cm)</option><option value="qr_3cm">3. QR Code (3 × 3 cm)</option></select></label>
            <button type="button" data-reset-position class="self-end rounded-lg border border-blue-200 bg-blue-50 p-2 text-sm text-blue-800">Letakkan di tengah halaman</button>
        </div>
        <div data-page-picker class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm" aria-live="polite"></div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-slate-700">
            <p>Format 1: QR Code 2 × 2 cm dengan teks identitas penandatangan. Format 2: QR Code saja 2 × 2 cm. Format 3: QR Code saja 3 × 3 cm. Semua ukuran dihitung otomatis dan tidak menggunakan ukuran manual.</p>
            <p class="mt-2">Dokumen baru menggunakan cakupan semua halaman secara default. Jika memilih cakupan khusus, centang lebih dari satu halaman—misalnya halaman 1, 3, dan 5. Posisi tiap halaman disimpan terpisah dan dapat berbeda sesuai penempatan Anda.</p>
            <p class="mt-2">Footer simulasi ditambahkan pada pita baru setinggi 1,2 cm di bawah setiap halaman. Ukuran halaman bertambah tanpa mengecilkan isi surat. Blok spesimen harus berada di area surat, bukan di footer.</p>
            <p class="mt-2">Jika PDF memuat placeholder seperti <code>${tte:signer:1}</code>, posisi awal akan dideteksi otomatis saat file diunggah. Anda tetap dapat menyesuaikannya melalui preview. Identitas disimpan saat posisi dikonfirmasi; perubahan profil berikutnya tidak mengubah dokumen ini.</p>
        </div>
        <p data-dimension-hint class="text-xs text-slate-500"></p>
        <div data-signer-progress class="flex flex-wrap gap-2 text-xs"></div>
        <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="placement-checklist-title">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 id="placement-checklist-title" class="text-sm font-semibold text-slate-900">Checklist posisi QR</h3>
                    <p class="text-xs text-slate-600">Klik status halaman untuk langsung membuka signer dan halaman tersebut.</p>
                </div>
                <span data-checklist-summary class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700">Memeriksa posisi…</span>
            </div>
            <div data-page-checklist class="grid gap-3 md:grid-cols-2" aria-live="polite"></div>
        </section>
        <p data-placement-status role="status" class="text-sm text-blue-800"></p>
        <div data-preview-scroll class="overflow-auto rounded border border-slate-300 bg-slate-100 p-3">
            <div data-page-surface class="relative mx-auto bg-white" style="width:760px; touch-action:none">
                <img data-page-image alt="Preview PDF sumber" draggable="false" class="block h-auto w-full select-none">
                <div data-blocks class="absolute inset-0"></div>
            </div>
        </div>
        <form action="{{ route('documents.pdf.place', $document) }}" method="POST" data-position-form class="space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
            <input type="hidden" name="source_sha256" value="{{ $cycle->original_sha256 }}">
            <div data-position-inputs></div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmed" value="1" required class="mt-1">Saya telah memeriksa seluruh halaman, posisi semua signer, dan memastikan blok tidak menutupi isi dokumen.</label>
            <p data-placement-status role="status" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-blue-800"></p>
            <button data-confirm-positions class="rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Konfirmasi seluruh posisi</button>
        </form>
    </section>
    @endif
</div>
@endsection
