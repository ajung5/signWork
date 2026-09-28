<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Verifikasi PDF · SignWork</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5 text-slate-900">
<main class="mx-auto max-w-3xl space-y-6 rounded-2xl bg-white p-6 shadow-sm sm:p-10">
    <p class="text-sm font-semibold text-blue-700">SIGNWORK · VERIFIKASI FILE</p>
    <h1 class="text-2xl font-semibold">{{ $cycle->status === 'signed' ? 'Simulasi selesai' : ($cycle->status === 'rejected' ? 'Siklus ditolak' : 'Dokumen masih diproses') }}</h1>
    <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"><strong>MOCK — BUKAN TTE SAH.</strong> Halaman ini hanya menunjukkan catatan simulasi dan kesesuaian hash file. Tidak membuktikan validitas sertifikat, identitas kriptografis, atau keabsahan TTE BSrE.</p>
    <dl class="grid gap-3 text-sm"><div><dt class="text-slate-500">Dokumen</dt><dd class="font-semibold">{{ $cycle->title }}</dd></div><div><dt class="text-slate-500">Nomor / Siklus</dt><dd>{{ $cycle->document_number ?? '—' }} / {{ $cycle->number }}</dd></div></dl>
    <ol class="space-y-2">@foreach($cycle->signatures as $step)<li class="rounded-lg border border-slate-200 p-3 text-sm">{{ $step->sequence }}. {{ $step->name_snapshot }} <x-workflow-status :status="$step->status" /> @if($step->acted_at)<span class="block text-slate-500">{{ $step->acted_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB</span>@endif</li>@endforeach</ol>
    <section class="rounded-xl border border-violet-200 bg-violet-50 p-4">
        <h2 class="font-semibold text-violet-900">Informasi posisi spesimen QR</h2>
        <p class="mt-1 text-sm text-violet-800">QR hanya muncul pada cakupan halaman yang ditentukan untuk masing-masing penandatangan.</p>
        <ul class="mt-3 space-y-2 text-sm text-slate-700">
            @foreach($cycle->signatures as $step)
                @php
                    $scopeLabel = match($step->specimen_scope) {
                        'all_pages' => 'semua halaman',
                        'selected_pages' => 'halaman ' . implode(', ', array_map('intval', $step->specimen_pages ?? [])),
                        'selected_page' => 'halaman ' . (int) ($step->page ?? 0),
                        default => 'cakupan tidak tersedia',
                    };
                @endphp
                <li><span class="font-semibold">{{ $step->sequence }}. {{ $step->name_snapshot }}</span>: specimen pada {{ $scopeLabel }}.</li>
            @endforeach
        </ul>
    </section>
    @if($cycle->status === 'signed')
        <div><h2 class="text-sm font-semibold">SHA-256 PDF final</h2><code class="mt-2 block break-all rounded-lg bg-slate-100 p-3 text-xs">{{ $cycle->final_sha256 }}</code></div>
        <form method="POST" action="{{ route('verification.compare', $cycle->public_id) }}" class="space-y-3" data-hash-form>
            @csrf
            <h2 class="font-semibold">Bandingkan file Anda</h2>
            <p class="text-sm text-slate-600">File dihitung di browser dan tidak diunggah. Hanya hash yang dikirim untuk dibandingkan. Batas 20 MB.</p>
            <input type="file" accept=".pdf,application/pdf" data-hash-file class="block w-full rounded border border-slate-300 p-2">
            <label class="block text-sm">Atau tempel SHA-256<input name="sha256" data-hash-input pattern="[a-fA-F0-9]{64}" required maxlength="64" class="mt-2 block w-full rounded border border-slate-300 p-3 font-mono text-xs"></label>
            <p data-hash-status role="status" class="text-sm text-slate-600"></p>
            <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Bandingkan hash</button>
        </form>
        @if(isset($match))<p role="status" class="rounded-lg border p-4 font-semibold {{ $match ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">{{ $match ? 'Hash cocok dengan PDF final simulasi yang tercatat.' : 'Hash tidak cocok. File berbeda dari PDF final simulasi yang tercatat.' }}</p>@endif
        @error('sha256')<p class="text-red-700">{{ $message }}</p>@enderror
    @else
        <p class="text-sm text-slate-600">Verifikasi hash final belum tersedia. Tunggu seluruh signer menyelesaikan proses.</p>
    @endif
</main></body></html>
