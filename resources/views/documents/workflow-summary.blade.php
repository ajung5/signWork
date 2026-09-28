<section class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold">PDF & Alur Berurutan</h2>
        @can('update', $document)
            @if ($document->isDraft())
                <a href="{{ $cycle && $cycle->positions_confirmed_at ? route('documents.pdf.review', $document) : route('documents.pdf.edit', $document) }}"
                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">
                    @if (!$cycle)
                        Atur PDF & peserta
                    @elseif($cycle->positions_confirmed_at)
                        Tinjau Posisi Spesiment
                    @else
                        Atur Posisi Spesiment
                    @endif
                </a>
            @endif
        @endcan
    </div>

    @php
        $workflowStage = match (true) {
            $document->sent_at !== null => 4,
            $document->isSigned() => 3,
            $document->isWaitingSignature() || $document->isSigning() || $document->isApproved() => 2,
            $document->isWaitingApproval() || $document->isSubmitted() => 1,
            default => 0,
        };
        $workflowSteps = [
            ['label' => 'Draft', 'description' => 'Dokumen & peserta'],
            ['label' => 'Verifikasi', 'description' => 'Pemeriksaan berurutan'],
            ['label' => 'Tanda tangan', 'description' => 'TTE setiap signer'],
            ['label' => 'Dikirim', 'description' => 'Masuk ke penerima'],
        ];
    @endphp

    <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-4" data-workflow-timeline>
        @foreach ($workflowSteps as $index => $step)
            @php
                $isComplete = $index < $workflowStage;
                $isCurrent = $index === $workflowStage;
            @endphp
            <div @class([
                'rounded-lg border p-3',
                'border-emerald-200 bg-emerald-50' => $isComplete,
                'border-blue-300 bg-blue-50 ring-1 ring-blue-200' => $isCurrent,
                'border-slate-200 bg-slate-50' => !$isComplete && !$isCurrent,
            ])>
                <div class="flex items-center gap-2">
                    <span @class([
                        'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                        'bg-emerald-600 text-white' => $isComplete,
                        'bg-blue-700 text-white' => $isCurrent,
                        'bg-slate-200 text-slate-500' => !$isComplete && !$isCurrent,
                    ]) aria-hidden="true">
                        {{ $isComplete ? '✓' : $index + 1 }}
                    </span>

                    <p class="text-sm font-semibold text-slate-900">
                        {{ $step['label'] }}
                    </p>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    {{ $step['description'] }}
                </p>
            </div>
        @endforeach
    </div>

    @if (!$cycle)
        @if ($document->requires_pdf_workflow)
            <p class="text-sm text-blue-800">Langkah berikutnya: unggah PDF, tentukan urutan verifikator/signer, dan
                konfirmasi posisi QR melalui tombol pengaturan di atas.</p>
        @else
            <p class="text-sm text-slate-600">Dokumen ini masih memakai alur metadata lama. Untuk workflow PDF, buka
                pengaturan PDF saat Draft atau setelah dikembalikan. Mode lama tidak menghasilkan TTE atau PDF final.
            </p>
        @endif
    @else
        @php
            $previewVersion = $cycle->status === 'signed' ? 'final' : ($cycle->current_path ? 'current' : 'original');
            $previewLabel =
                $previewVersion === 'final'
                    ? 'Preview PDF final'
                    : ($previewVersion === 'current'
                        ? 'Preview PDF tahap berjalan'
                        : 'Preview dokumen sumber');
        @endphp
        <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Provider: MOCK — simulasi, bukan TTE BSrE yang sah.
            QR membandingkan hash file; bukan validasi sertifikat elektronik.</p>
        @if (!$document->isDraft() || !$cycle->positions_confirmed_at)
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-blue-900">Preview dokumen dan posisi spesimen</h3>
                        <p class="mt-1 text-sm text-blue-800">Setiap tahap workflow dapat memeriksa PDF yang sedang
                            berjalan dan posisi specimen QR yang akan digunakan.</p>
                    </div>
                    <a href="{{ route('documents.pdf.viewer', [$document, 'version' => $previewVersion]) }}"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">{{ $previewLabel }}</a>
                </div>
            </div>
        @endif
        @if ($document->isSigned())
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <a href="{{ route('verification.show', $cycle->public_id) }}" class="preview-action">Buka verifikasi QR</a>
            </div>
        @endif
        <p class="break-all text-xs text-slate-500">Siklus {{ $cycle->number }} · SHA-256 sumber:
            {{ $cycle->original_sha256 }}</p>
        @if ($document->isDraft())
            @if ($cycle->positions_confirmed_at)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="font-semibold text-emerald-900">Siap diajukan</p>
                    <p class="mt-1 text-sm text-emerald-800">Peserta dan posisi spesimen sudah dikonfirmasi. Gunakan
                        tombol <span class="font-semibold">Tinjau posisi Spesiment</span> di header untuk melihat posisi
                        tersimpan, atau pilih edit untuk mengubahnya.</p>
                </div>
            @else
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">Draft belum siap diajukan</p>
                    <p class="mt-1 text-sm text-amber-800">Lengkapi peserta, cakupan halaman, dan posisi QR melalui
                        tombol <span class="font-semibold">Lengkapi posisi Spesiment</span> di header.</p>
                </div>
            @endif
        @endif
        <div class="grid gap-5 md:grid-cols-2">
            @foreach (['approvals' => 'Verifikasi', 'signatures' => 'Tanda Tangan'] as $relation => $label)
                <div>
                    <h3 class="mb-2 text-sm font-semibold">{{ $label }}</h3>
                    <ol class="space-y-2 text-sm">
                        @foreach ($cycle->$relation as $step)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3"><span
                                        class="font-medium">{{ $step->sequence }}. {{ $step->name_snapshot }}</span>
                                    <x-workflow-status :status="$step->status" />
                                </div>
                                @if ($relation === 'signatures')
                                    @php
                                        $scopeLabel = match ($step->specimen_scope) {
                                            'all_pages' => 'Semua halaman',
                                            'selected_pages' => 'Halaman ' .
                                                implode(', ', array_map('intval', $step->specimen_pages ?? [])),
                                            'selected_page' => 'Halaman ' . (int) ($step->page ?? 0),
                                            default => 'Belum ditentukan'
                                        };
                                    @endphp
                                    <div
                                        class="mt-3 rounded-lg border border-violet-200 bg-white p-3 text-xs text-slate-600">
                                        <p><span class="font-semibold text-violet-900">Posisi spesimen:</span>
                                            {{ $scopeLabel }}</p>
                                        @if ($step->specimen_scope === 'selected_pages' && is_array($step->specimen_positions))
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($step->specimen_positions as $pageNumber => $position)
                                                    <span
                                                        class="rounded border border-slate-200 bg-slate-50 px-2 py-1">Hal.
                                                        {{ $pageNumber }} · X
                                                        {{ number_format((float) ($position['x'] ?? 0), 1) }} · Y
                                                        {{ number_format((float) ($position['y'] ?? 0), 1) }}</span>
                                                @endforeach
                                            </div>
                                        @elseif($step->x !== null && $step->y !== null)
                                            <p class="mt-1">Koordinat: X {{ number_format((float) $step->x, 1) }} · Y
                                                {{ number_format((float) $step->y, 1) }}</p>
                                        @endif
                                    </div>
                                @endif
                                @if ($step->acted_at)
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $step->acted_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB</p>
                                @endif
                                @if ($step->rejection_reason)
                                    <p class="mt-1 text-red-700">{{ $step->rejection_reason }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
        @can('sign', $document)
            <form action="{{ route('documents.sign', $document) }}" method="POST"
                id="workflow-action" class="scroll-mt-6 space-y-3 rounded-lg border border-amber-200 p-4"
                data-single-submit>
                @csrf <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                <div class="rounded-lg bg-amber-50 p-3 text-sm">Gunakan passphrase demo <code
                        class="font-semibold">{{ config('signwork.mock_passphrase') }}</code>. Jangan masukkan passphrase
                    BSrE asli.</div>
                <label class="block text-sm font-medium" for="sign-passphrase">Passphrase simulasi</label>
                <input id="sign-passphrase" name="passphrase" type="password" required maxlength="200" autocomplete="off"
                    class="w-full rounded-lg border border-slate-300 p-3" aria-describedby="passphrase-help">
                <p id="passphrase-help" class="text-xs text-slate-600">Diverifikasi saat tombol ditekan. Jika salah, proses
                    tidak dilanjutkan. Nilainya tidak disimpan atau ditampilkan kembali.</p>
                <label class="flex gap-2 text-sm"><input type="checkbox" name="mock_acknowledged" value="1"
                        required>Saya memahami bahwa aksi ini hanya simulasi signing dan tidak menggunakan sertifikat
                    BSrE.</label>
                <button class="rounded-lg bg-violet-700 px-4 py-2 font-semibold text-white">Jalankan simulasi tanda
                    tangan</button>
            </form>
        @endcan
        @if ($cycle->qr_mode === 'legacy_all')
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Siklus lama: QR sudah dipasang dengan metode
                sebelumnya. File dan bukti yang telah ditandatangani dipertahankan. QR bertahap berlaku untuk siklus
                baru/belum ditandatangani.</p>
        @endif
        @if ($document->sent_at)
            <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">Dikirim ke penerima pada
                {{ $document->sent_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB.</p>
        @elseif($document->isSigned())
            <p class="text-sm text-slate-600">Signing selesai. Dokumen belum masuk inbox penerima sampai pemilik atau
                signer terakhir menekan Kirim.</p>
            @can('send', $document)
                <form method="POST" action="{{ route('documents.send', $document) }}" id="workflow-action"
                    class="scroll-mt-6" data-single-submit>
                    @csrf <input type="hidden" name="cycle_token" value="{{ $cycle->public_id }}">
                    <button class="rounded-lg bg-emerald-700 px-4 py-3 font-semibold text-white">Kirim dokumen final ke
                        {{ $document->destination?->name }}</button>
                </form>
            @endcan
        @endif
        @if ($cycle->final_sha256)
            <p class="break-all text-xs text-slate-600">SHA-256 final: {{ $cycle->final_sha256 }}</p>
        @endif
        @if ($cycles->count() > 1)
            <details>
                <summary class="cursor-pointer text-sm font-semibold">Riwayat siklus sebelumnya</summary>
                @foreach ($cycles as $history)
                    @if ($history->number !== $cycle->number)
                        <div class="mt-3 rounded-lg border border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2"><strong>Siklus
                                    {{ $history->number }}</strong><x-workflow-status :status="$history->status" /></div>
                            <p class="break-all text-xs">SHA-256 sumber: {{ $history->original_sha256 }}</p>
                            @foreach ($history->approvals as $step)
                                <p>{{ $step->sequence }}. {{ $step->name_snapshot }} <x-workflow-status
                                        :status="$step->status" /> {{ $step->rejection_reason }}</p>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </details>
        @endif
    @endif
</section>
