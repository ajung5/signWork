@extends('layouts.app')
@php
    $pageLabel = match ($selectedType->value) {
        'signer' => 'Penandatangan',
        'destination' => 'Tujuan Naskah',
        'approver' => 'Verifikator',
    };
@endphp
@section('title', $pageLabel . ' - SignWork')
@section('content')
    <div class="mx-auto max-w-375">
        <p class="text-sm font-medium text-blue-700">Master / Pengguna</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl">{{ $pageLabel }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Kelola daftar {{ $pageLabel }} dan tentukan pengguna
            default untuk dokumen baru.</p>
        <div class="mt-7 space-y-6">
            @foreach ($types as $type)
                @php
                    $typeEntries = $entries;
                    $availableUsers = $users;
                    $activeForm = old('type') === $type->value;
                    $description = match ($type->value) {
                        'destination' => 'Penerima dokumen final setelah dikirim.',
                        'approver' => 'Pengguna yang memeriksa dan memverifikasi dokumen.',
                        'signer' => 'Pengguna yang menandatangani dokumen.',
                    };
                @endphp
                <section id="group-{{ $type->value }}" data-master-group="{{ $type->value }}"
                    class="scroll-mt-6 overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm">
                    <div class="border-b border-blue-100 bg-blue-50 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-blue-900">{{ $pageLabel }}</h2>
                            <span
                                class="rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-semibold text-blue-800">{{ number_format($typeEntries->total()) }}
                                hasil</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $description }}</p>
                    </div>
                    <form action="{{ route('workflow-settings.store') }}" method="POST"
                        class="space-y-3 border-b border-slate-200 p-5">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type->value }}">
                        <label for="user-{{ $type->value }}" class="block text-sm font-semibold text-slate-700">Tambah
                            pengguna</label>
                        <select id="user-{{ $type->value }}" name="target_user_id" required @disabled($availableUsers->isEmpty())
                            class="block w-full rounded-xl border border-slate-300 bg-slate-50 p-3 text-sm disabled:opacity-60">
                            <option value="">
                                {{ $availableUsers->isEmpty() ? 'Semua pengguna sudah terdaftar' : 'Pilih pengguna' }}
                            </option>
                            @foreach ($availableUsers as $user)
                                <option value="{{ $user->id }}" @selected($activeForm && old('target_user_id') == $user->id)>{{ $user->name }} —
                                    {{ $user->email }}{{ $user->id === auth()->id() ? ' (Saya)' : '' }}</option>
                            @endforeach
                        </select>
                        @if ($activeForm)
                            @error('target_user_id')
                                <p class="text-sm text-rose-700">{{ $message }}</p>
                            @enderror
                        @endif
                        <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox"
                                name="is_default" value="1" @checked($activeForm && old('is_default'))> Jadikan pilihan
                            default</label>
                        <button type="submit" @disabled($availableUsers->isEmpty())
                            class="w-full rounded-xl bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">+
                            Tambah ke {{ $pageLabel }}</button>
                        <p class="text-xs leading-5 text-slate-500">Pengguna yang sudah ada di grup ini tidak ditampilkan
                            pada pilihan tambah. Entri pertama otomatis menjadi default.</p>
                    </form>
                    <form action="{{ route('workflow-settings.group', ['type' => $type->value]) }}" method="GET"
                        class="grid gap-4 border-b border-slate-200 p-5 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <div>
                            <label for="q"
                                class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Cari
                                pengguna</label>
                            <input id="q" name="q" type="search" value="{{ $search }}"
                                placeholder="Cari nama atau email..."
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none focus:border-cyan-400 focus:bg-white focus:ring-4 focus:ring-cyan-100">
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100">Cari</button>
                            @if ($search !== '')
                                <a href="{{ route('workflow-settings.group', ['type' => $type->value]) }}"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Reset</a>
                            @endif
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    @foreach (['No.', 'Nama', 'Email', 'Pilihan'] as $heading)
                                        <th scope="col"
                                            class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                            {{ $heading }}</th>
                                    @endforeach
                                    <th scope="col"
                                        class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($typeEntries as $entry)
                                    <tr class="transition hover:bg-cyan-50/30">
                                        <td class="px-5 py-4 text-sm text-slate-500 sm:px-6">
                                            {{ $typeEntries->firstItem() + $loop->index }}</td>
                                        <td class="min-w-48 px-5 py-4 text-sm font-semibold text-slate-900 sm:px-6">
                                            {{ $entry->target->name }}
                                            @if ($entry->target_user_id === auth()->id())
                                                <span
                                                    class="ml-2 rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-800">Saya</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-sm text-slate-500 sm:px-6">{{ $entry->target->email }}
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            @if ($entry->is_default)
                                                <span
                                                    class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Default</span>
                                            @else
                                                <span
                                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Alternatif</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('workflow-settings.entry.edit', $entry) }}"
                                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">Edit</a>
                                                <form action="{{ route('workflow-settings.entry.destroy', $entry) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Hapus pengguna dari grup ini? Dokumen yang sudah dibuat tidak berubah.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-14 text-center text-sm text-slate-500">
                                            {{ $search !== '' ? 'Tidak ada pengguna yang cocok dengan pencarian.' : 'Belum ada pengguna di grup ' . $pageLabel . '.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @if ($typeEntries->hasPages())
                    <div>{{ $typeEntries->links() }}</div>
                @endif
            @endforeach
        </div>
    </div>
@endsection
