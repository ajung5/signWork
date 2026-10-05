@extends('layouts.app')

@section('title', 'Manajemen User - SignWork')

@section('content')
    <div class="mx-auto max-w-375">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-700">
                    Administrasi
                </p>

                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    Manajemen User
                </h1>

                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Tambah, edit, dan hapus akun User operasional. Penghapusan diblokir jika akun masih terhubung dengan
                    dokumen atau Data Master workflow.
                </p>
            </div>

            <a href="{{ route('admin.users.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100">
                <span class="text-lg leading-none">+</span>
                Tambah User
            </a>
        </div>

        <section class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="font-semibold text-slate-900">
                    Daftar Akun
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Superadmin dan Admin dapat memantau seluruh akun. Akun admin hanya dapat dikelola oleh Superadmin.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Nama
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Email
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Jabatan / Unit kerja</th>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Pangkat / Golongan</th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Role
                            </th>

                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Dokumen
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Dibuat
                            </th>

                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-6">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @forelse ($users as $user)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4 text-sm font-semibold text-slate-900 sm:px-6">
                                    {{ $user->name }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600 sm:px-6">
                                    {{ $user->email }}
                                </td>

                                <td class="min-w-56 px-5 py-4 text-sm text-slate-700 sm:px-6">
                                    <p>{{ $user->jabatan ?: '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $user->unit_kerja ?: '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600 sm:px-6">
                                    {{ $user->pangkat ?: '—' }} @if ($user->golongan)
                                        ({{ $user->golongan }})
                                    @endif
                                </td>

                                <td class="px-5 py-4 sm:px-6">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                        'border-indigo-200 bg-indigo-50 text-indigo-700' => $user->isAdmin(),
                                        'border-slate-200 bg-slate-50 text-slate-700' => !$user->isAdmin(),
                                    ])>
                                        {{ $user->role->label() }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800 sm:px-6">
                                    {{ number_format($user->documents_count) }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500 sm:px-6">
                                    {{ $user->created_at->format('d M Y, H:i') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    @if ($user->isAdmin() && !auth()->user()->isSuperadmin())
                                        <span class="text-xs font-medium text-slate-400">
                                            Protected
                                        </span>
                                    @else
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.users.edit', $user) }}"
                                                class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800 transition hover:bg-blue-100">
                                                Edit
                                            </a>

                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                                onsubmit="return confirm('Hapus user ini? User yang masih terhubung ke dokumen atau Data Master tidak dapat dihapus.');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    class="inline-flex rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center text-sm text-slate-500">
                                    Belum ada user.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($users->hasPages())
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
