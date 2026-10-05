@extends('layouts.app')
@section('title', 'Log Aktivitas - SignWork')
@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
        <h1 class="text-2xl font-semibold">Log Aktivitas</h1>
        <p class="text-sm text-slate-600">Respons tindakan aplikasi dan kegagalan akses sejak patch dipasang. Passphrase,
            password, isi file, dan request body tidak dicatat.</p>
        <form class="flex flex-wrap gap-3" method="GET">
            <select name="outcome" aria-label="Hasil" class="rounded-lg border border-slate-300 p-2">
                <option value="">Semua hasil</option>
                <option value="success" @selected(request('outcome') === 'success')>Berhasil</option>
                <option value="error" @selected(request('outcome') === 'error')>Gagal</option>
            </select>
            <input name="event" value="{{ request('event') }}" placeholder="Nama aksi, contoh documents.sign"
                aria-label="Nama aksi" class="rounded-lg border border-slate-300 p-2">
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Filter</button><a
                href="{{ route('admin.activity.index') }}" class="p-2 text-blue-700">Reset</a>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['Waktu WIB', 'Aktor', 'Aksi', 'Hasil / HTTP', 'Respons'] as $heading)
                            <th class="p-3">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-t border-slate-100">
                            <td class="whitespace-nowrap p-3">
                                {{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</td>
                            <td class="p-3">{{ $log->user?->name ?? 'Tamu / akun dihapus' }}</td>
                            <td class="p-3"><code>{{ $log->event }}</code>
                                @if ($log->document_uuid)
                                    <p class="break-all text-xs text-slate-500">{{ $log->document_uuid }}</p>
                                @endif
                            </td>
                            <td class="p-3 {{ $log->outcome === 'error' ? 'text-red-700' : 'text-emerald-700' }}">
                                {{ $log->outcome }} · {{ $log->http_status }}</td>
                            <td class="p-3">{{ $log->message }}</td>
                    </tr>@empty<tr>
                            <td colspan="5" class="p-6 text-center text-slate-500">Belum ada aktivitas yang sesuai
                                filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
