@extends('layouts.app')

@section('title', 'Validasi Dokumen - SignWork')

@section('content')
    @php
        $inspection = $inspection ?? null;
        $inspectionSignatures = data_get($inspection, 'signatures', []);
        $inspectionFileSize = $fileSize ?? null;

        $inspectionFileSizeLabel = $inspectionFileSize === null
            ? '—'
            : ($inspectionFileSize >= 1048576
                ? number_format($inspectionFileSize / 1048576, 2, ',', '.') . ' MB'
                : number_format($inspectionFileSize / 1024, 2, ',', '.') . ' KB');
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">
                Validasi TTE
            </p>

            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">
                Validasi Dokumen
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-600">
                Unggah file PDF untuk memeriksa apakah dokumen memiliki tanda tangan elektronik.
            </p>
        </div>

        <form method="POST"
            action="{{ route('validation.lookup') }}"
            enctype="multipart/form-data"
            class="space-y-4 rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">

            @csrf

            <label class="block text-sm font-semibold text-slate-800"
                for="validation-document">
                File PDF yang akan diperiksa
            </label>

            <input id="validation-document"
                name="document"
                type="file"
                accept=".pdf,application/pdf"
                required
                class="block w-full rounded-lg border border-slate-300 px-4 py-3 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:font-semibold file:text-emerald-700 focus:border-emerald-500 focus:ring-emerald-500">

            <p class="text-xs text-slate-500">
                Format PDF, ukuran maksimal 20 MB.
            </p>

            @error('document')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button class="rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800">
                Periksa TTE
            </button>
        </form>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
            <p class="font-semibold text-slate-800">Catatan</p>

            <p class="mt-1">
                Pemeriksaan membaca tanda tangan digital dan sertifikat yang tertanam pada PDF.
                Hasil “TTE terdeteksi” menunjukkan adanya tanda tangan elektronik pada file.
            </p>
        </div>

        @if ($inspection !== null)
            <section class="rounded-2xl border
                {{ count($inspectionSignatures) > 0
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-amber-200 bg-amber-50' }}
                p-6 shadow-sm">

                @if (count($inspectionSignatures) > 0)
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 text-xl text-emerald-700">✓</span>

                        <div>
                            <h2 class="text-xl font-semibold text-emerald-900">
                                TTE terdeteksi
                            </h2>

                            <p class="mt-1 text-sm leading-6 text-emerald-800">
                                File ini memiliki
                                {{ count($inspectionSignatures) }}
                                tanda tangan digital yang tertanam.
                            </p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-3 rounded-xl border border-emerald-200 bg-white p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Nama file</dt>
                            <dd class="mt-1 break-all font-semibold">
                                {{ $fileName ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Ukuran file</dt>
                            <dd class="mt-1">
                                {{ $inspectionFileSizeLabel }}
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">SHA-256 file</dt>
                            <dd class="mt-1 break-all font-mono text-xs">
                                {{ $fileHash ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 space-y-3">
                        @foreach ($inspectionSignatures as $index => $signature)
                            <div class="rounded-xl border border-emerald-200 bg-white p-4 text-sm">
                                <p class="font-semibold text-slate-900">
                                    Tanda tangan {{ $index + 1 }}
                                </p>

                                @if (empty($signature['certificate_available']))
                                    <p class="mt-2 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800">
                                        TTE terdeteksi, tetapi metadata sertifikat belum dapat dibaca oleh server.
                                        Pastikan OpenSSL tersedia pada PATH server.
                                    </p>
                                @endif

                                <dl class="mt-3 space-y-2">
                                    <div>
                                        <dt class="text-slate-500">Subject DN</dt>
                                        <dd class="mt-1 break-all text-xs">
                                            {{ $signature['subject_dn'] ?? '—' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">Issuer DN</dt>
                                        <dd class="mt-1 break-all text-xs">
                                            {{ $signature['issuer_dn'] ?? '—' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">SHA-1 fingerprint</dt>
                                        <dd class="mt-1 break-all font-mono text-xs">
                                            {{ $signature['fingerprint_sha1'] ?? '—' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt class="text-slate-500">Validity</dt>
                                        <dd class="mt-1 text-xs">
                                            {{ $signature['not_before'] ?? '—' }}
                                            —
                                            {{ $signature['not_after'] ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 text-xl text-amber-700">!</span>

                        <div>
                            <h2 class="text-xl font-semibold text-amber-900">
                                TTE belum terdeteksi
                            </h2>

                            <p class="mt-1 text-sm leading-6 text-amber-800">
                                Tidak ditemukan tanda tangan digital atau sertifikat TTE
                                yang tertanam pada file PDF ini.
                            </p>
                        </div>
                    </div>

                    <dl class="mt-5 grid gap-3 rounded-xl border border-amber-200 bg-white p-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Nama file</dt>
                            <dd class="mt-1 break-all font-semibold">
                                {{ $fileName ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-500">Ukuran file</dt>
                            <dd class="mt-1">
                                {{ $inspectionFileSizeLabel }}
                            </dd>
                        </div>
                    </dl>
                @endif
            </section>
        @endif
    </div>
@endsection
