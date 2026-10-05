@props(['status'])
@php
    [$label, $tone, $symbol] = match ($status) {
        'approved' => ['Terverifikasi', 'border-emerald-200 bg-emerald-50 text-emerald-800', '✓'],
        'signed' => ['Ditandatangani', 'border-blue-200 bg-blue-100 text-blue-900', '✓'],
        'rejected' => ['Ditolak', 'border-rose-200 bg-rose-50 text-rose-800', '×'],
        'failed', 'sign_failed' => ['Gagal', 'border-rose-200 bg-rose-50 text-rose-800', '!'],
        'not_processed' => ['Tidak diproses', 'border-slate-200 bg-slate-100 text-slate-600', '—'],
        'pending' => ['Menunggu giliran', 'border-amber-200 bg-amber-50 text-amber-800', '◷'],
        'waiting_approval' => ['Menunggu verifikasi', 'border-amber-200 bg-amber-50 text-amber-800', '◷'],
        'waiting_signature', 'signing' => ['Menunggu tanda tangan', 'border-blue-200 bg-blue-50 text-blue-800', '◷'],
        'draft' => ['Draft', 'border-slate-200 bg-slate-100 text-slate-700', '•'],
        'submitted' => ['Diajukan', 'border-cyan-200 bg-cyan-50 text-cyan-800', '↑'],
        default => ['Dalam proses', 'border-slate-200 bg-slate-50 text-slate-700', '•'],
    };
@endphp
<span
    {{ $attributes->class(['inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm', $tone]) }}>
    <span aria-hidden="true">{{ $symbol }}</span>{{ $label }}
</span>
