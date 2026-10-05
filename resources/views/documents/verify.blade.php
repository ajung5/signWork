@php
    $isBsre = config('signwork.provider') === 'bsre';
    $signedCount = $cycle->signatures->where('status', 'signed')->count();
    $totalSignatures = $cycle->signatures->count();
    $isValid = $cycle->status === 'signed' && $signedCount > 0 && $signedCount === $totalSignatures;
    $fileSizeLabel =
        $fileSize === null
            ? '—'
            : ($fileSize >= 1048576
                ? number_format($fileSize / 1048576, 2, ',', '.') . ' MB'
                : number_format($fileSize / 1024, 2, ',', '.') . ' KB');
@endphp
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Validasi Dokumen · SignWork</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 p-4 text-slate-900 sm:p-6">
    <main class="mx-auto max-w-2xl space-y-4">
        <section class="rounded-3xl bg-white p-5 shadow-sm sm:p-8">
            <div class="flex flex-col items-center text-center">
                <div
                    class="flex h-28 w-28 items-center justify-center rounded-full bg-emerald-100 ring-8 ring-emerald-50">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        class="h-16 w-16 text-emerald-500" stroke-width="1.7">
                        <path d="M12 3.5 19 6v5.4c0 4.3-2.9 7.8-7 9.1-4.1-1.3-7-4.8-7-9.1V6l7-2.5Z" />
                        <path d="m8.5 12 2.2 2.2 4.8-5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <h1 class="mt-8 text-3xl font-bold tracking-tight text-slate-900">
                    {{ $isValid ? 'Dokumen Valid' : 'Dokumen Belum Selesai' }}
                </h1>
            </div>

            <div class="mt-8 rounded-2xl border border-slate-200 p-5 sm:p-6">
                <div class="flex items-center gap-3 text-slate-700">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5"
                            stroke-width="2.5">
                            <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>{{ $isValid ? 'Keaslian dokumen terjaga' : 'Dokumen masih diproses' }}</span>
                </div>
                <div class="mt-5 flex items-center gap-3 text-slate-700">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5"
                            stroke-width="2.5">
                            <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span>{{ $signedCount }} tanda tangan tervalidasi</span>
                </div>
            </div>

            <p class="mt-7 text-center text-lg leading-8 text-slate-600">
                @if ($isBsre)
                    Tanda tangan elektronik BSrE {{ $isValid ? 'valid' : 'belum lengkap' }}. Dokumen
                    {{ $isValid ? 'tidak berubah sejak ditandatangani.' : 'belum selesai ditandatangani oleh seluruh signer.' }}
                @else
                    <strong class="text-amber-800">MOCK — BUKAN TTE SAH.</strong>
                    Halaman ini hanya menunjukkan catatan simulasi dan kesesuaian hash file.
                @endif
            </p>
        </section>

        <details class="group rounded-2xl bg-white shadow-sm" open>
            <summary
                class="flex cursor-pointer list-none items-center gap-4 p-5 text-lg font-semibold sm:p-6 [&::-webkit-details-marker]:hidden">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7"
                    stroke-width="1.8">
                    <path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" />
                    <path d="M14 3.5V8h4M9 12h6M9 16h6" stroke-linecap="round" />
                </svg>
                <span class="flex-1">Informasi Dokumen</span>
                <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
            </summary>
            <dl class="grid gap-3 border-t border-slate-100 px-5 pb-6 pt-5 text-sm sm:grid-cols-2 sm:px-6">
                <div>
                    <dt class="text-slate-500">Nama dokumen</dt>
                    <dd class="mt-1 font-semibold">{{ $cycle->title }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Nomor</dt>
                    <dd class="mt-1">{{ $cycle->document_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Jumlah halaman</dt>
                    <dd class="mt-1">{{ $pageCount > 0 ? number_format($pageCount, 0, ',', '.') : '—' }} halaman</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Ukuran file</dt>
                    <dd class="mt-1">{{ $fileSizeLabel }}</dd>
                </div>
            </dl>
        </details>

        <details class="group rounded-2xl bg-white shadow-sm">
            <summary
                class="flex cursor-pointer list-none items-center gap-4 p-5 text-lg font-semibold sm:p-6 [&::-webkit-details-marker]:hidden">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7"
                    stroke-width="1.8">
                    <path
                        d="M6 18c0-4.4 3.6-8 8-8 2.2 0 4-1.8 4-4 0-1.7-1.3-3-3-3-2.5 0-4.7 3.5-6.1 6.8C7.5 13.5 5.7 16.1 5 18c-.5 1.4.2 2.5 1.5 2.5 1.9 0 4.2-2.1 6.3-5.2" />
                    <path d="M15 16h5M17.5 13.5v5" stroke-linecap="round" />
                </svg>
                <span class="flex-1">Informasi Tanda Tangan</span>
                <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
            </summary>
            <ol class="space-y-3 border-t border-slate-100 px-5 pb-6 pt-5 text-sm sm:px-6">
                @foreach ($cycle->signatures as $step)
                    <li class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-slate-900">
                                {{ $step->sequence }}. {{ $step->name_snapshot }}
                            </p>

                            <x-workflow-status :status="$step->status" />
                        </div>

                        <p class="mt-2 text-xs text-slate-500">
                            Penandatangan dokumen
                        </p>

                        @if ($step->acted_at)
                            <p class="mt-1 text-slate-500">
                                {{ $step->acted_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB
                            </p>
                        @endif

                        <button type="button" data-open-signer="signer-detail-{{ $step->id }}"
                            class="mt-4 rounded-lg border border-emerald-700 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                            Lihat detail tanda tangan
                        </button>
                    </li>
                @endforeach
            </ol>
        </details>

        @foreach ($cycle->signatures as $step)
            @php
                $signer = $step->user;
                $profile =
                    is_array($step->profile_snapshot) && $step->profile_snapshot !== []
                        ? $step->profile_snapshot
                        : [
                            'jabatan' => $signer?->jabatan,
                            'unit_kerja' => $signer?->unit_kerja,
                            'pangkat' => $signer?->pangkat,
                            'golongan' => $signer?->golongan,
                        ];
                $certificate = $certificateByStep[$step->id] ?? [];
                $certificateReady = $isBsre && $step->status === 'signed';
                $certificateEmbedded = $certificateReady && filled($certificate['subject_dn'] ?? null);
            @endphp
            <dialog id="signer-detail-{{ $step->id }}"
                class="w-[calc(100%-2rem)] max-w-xl rounded-2xl border-0 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
                <div class="border-b border-slate-100 p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Signer information
                            </p>
                            <h2 class="mt-1 text-xl font-semibold">{{ $step->name_snapshot }}</h2>
                        </div>
                        <button type="button" data-close-dialog
                            class="rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 hover:bg-slate-100"
                            aria-label="Tutup">&times;</button>
                    </div>
                </div>
                <div class="space-y-4 p-5 sm:p-6">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Nama penandatangan</dt>
                            <dd class="mt-1 font-semibold">{{ $step->name_snapshot }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">NIK</dt>
                            <dd class="mt-1">
                                {{ $signer?->nik ? substr($signer->nik, 0, 4) . '********' . substr($signer->nik, -4) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Jabatan</dt>
                            <dd class="mt-1">{{ $profile['jabatan'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Unit kerja</dt>
                            <dd class="mt-1">{{ $profile['unit_kerja'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Pangkat / Golongan</dt>
                            <dd class="mt-1">{{ $profile['pangkat'] ?? '—' }} / {{ $profile['golongan'] ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Waktu tanda tangan</dt>
                            <dd class="mt-1">
                                {{ $step->acted_at ? $step->acted_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') . ' WIB' : 'Belum ditandatangani' }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="rounded-xl border {{ $certificateReady ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4">
                        <p
                            class="text-xs font-semibold uppercase tracking-wide {{ $certificateReady ? 'text-emerald-700' : 'text-amber-700' }}">
                            Certificate status</p>
                        <p class="mt-1 font-semibold {{ $certificateReady ? 'text-emerald-900' : 'text-amber-900' }}">
                            {{ $certificateEmbedded ? 'Sertifikat BSrE terdeteksi pada PDF final' : ($certificateReady ? 'BSrE signing berhasil diterima' : 'Belum tersedia') }}
                        </p>
                        <p class="mt-1 text-sm {{ $certificateReady ? 'text-emerald-800' : 'text-amber-800' }}">
                            {{ $certificateEmbedded ? 'Metadata sertifikat dapat dilihat melalui tombol View Certificate.' : ($certificateReady ? 'Dokumen telah diproses melalui eSign Client BSrE.' : 'Detail sertifikat tersedia setelah penandatangan berhasil.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" data-close-dialog
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Tutup</button>
                        <button type="button" data-open-certificate="certificate-detail-{{ $step->id }}"
                            class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                            View Certificate
                        </button>
                    </div>
                </div>
            </dialog>

            <dialog id="certificate-detail-{{ $step->id }}"
                class="w-[calc(100%-2rem)] max-w-xl rounded-2xl border-0 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
                <div class="border-b border-slate-100 p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Certificate
                                detail</p>
                            <h2 class="mt-1 text-xl font-semibold">{{ $step->name_snapshot }}</h2>
                        </div>
                        <button type="button" data-close-dialog
                            class="rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 hover:bg-slate-100"
                            aria-label="Tutup">&times;</button>
                    </div>
                </div>
                <div class="space-y-4 p-5 sm:p-6">
                    <dl class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Certificate status</dt>
                            <dd
                                class="text-right font-semibold {{ $certificateReady ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $certificateEmbedded ? 'Sertifikat tertanam' : ($certificateReady ? 'Signing BSrE berhasil' : 'Belum tersedia') }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Provider</dt>
                            <dd class="text-right font-semibold">
                                {{ $isBsre ? 'eSign Client BSrE' : 'Mock (simulasi)' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">SHA-1 fingerprint</dt>
                            <dd class="max-w-[65%] break-all text-right font-mono text-xs">
                                {{ $certificate['fingerprint_sha1'] ?? 'Belum tersedia' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Issuer DN</dt>
                            <dd class="max-w-[65%] break-all text-right text-xs">
                                {{ $certificate['issuer_dn'] ?? 'Belum tersedia' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Subject DN</dt>
                            <dd class="max-w-[65%] break-all text-right text-xs">
                                {{ $certificate['subject_dn'] ?? 'Belum tersedia' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Validity</dt>
                            <dd class="max-w-[65%] text-right text-xs">
                                {{ $certificate['not_before'] ?? '—' }}<br>{{ $certificate['not_after'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Key usage</dt>
                            <dd class="text-right font-semibold">Digital Signature<br>Non-Repudiation</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4 p-4">
                            <dt class="text-slate-500">Transaction ID</dt>
                            <dd class="max-w-[60%] break-all text-right font-mono text-xs">
                                {{ $step->provider_transaction_id ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                        Data fingerprint dan DN dibaca dari sertifikat yang tertanam pada PDF final. SignWork tidak
                        menyimpan private key atau passphrase signer.
                    </p>
                    <div class="flex justify-end">
                        <button type="button" data-close-dialog
                            class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Tutup</button>
                    </div>
                </div>
            </dialog>
        @endforeach

        @if ($cycle->status === 'signed')
            <section class="rounded-2xl bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-sm font-semibold">SHA-256 PDF final</h2>
                <code
                    class="mt-2 block break-all rounded-lg bg-slate-100 p-3 text-xs">{{ $cycle->final_sha256 }}</code>
                <form method="POST" action="{{ route('verification.compare', $cycle->public_id) }}"
                    class="mt-5 space-y-3" data-hash-form>
                    @csrf
                    <h2 class="font-semibold">Bandingkan file Anda</h2>
                    <p class="text-sm text-slate-600">File dihitung di browser dan tidak diunggah. Hanya hash yang
                        dikirim untuk dibandingkan.</p>
                    <input type="file" accept=".pdf,application/pdf" data-hash-file
                        class="block w-full rounded border border-slate-300 p-2">
                    <label class="block text-sm">Atau tempel SHA-256
                        <input name="sha256" data-hash-input pattern="[a-fA-F0-9]{64}" required maxlength="64"
                            class="mt-2 block w-full rounded border border-slate-300 p-3 font-mono text-xs">
                    </label>
                    <p data-hash-status role="status" class="text-sm text-slate-600"></p>
                    <button class="rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white">Bandingkan
                        hash</button>
                </form>
                @if (isset($match))
                    <p role="status"
                        class="mt-4 rounded-lg border p-4 font-semibold {{ $match ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">
                        {{ $match ? 'Hash cocok dengan PDF final yang tercatat.' : 'Hash tidak cocok. File berbeda dari PDF final yang tercatat.' }}
                    </p>
                @endif
                @error('sha256')
                    <p class="text-red-700">{{ $message }}</p>
                @enderror
            </section>
        @else
            <p class="rounded-xl bg-white p-5 text-sm text-slate-600 shadow-sm">Validasi hash final tersedia setelah
                seluruh signer menyelesaikan proses.</p>
        @endif
    </main>
    <script>
        document.querySelectorAll('[data-open-signer], [data-open-certificate]').forEach((button) => {
            button.addEventListener('click', () => {
                const currentDialog = button.closest('dialog');
                const target = document.getElementById(button.dataset.openSigner || button.dataset
                    .openCertificate);
                if (currentDialog?.open) currentDialog.close();
                target?.showModal();
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });

        document.querySelectorAll('dialog').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        });
    </script>

</body>

</html>
