<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SignWork')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="app-shell min-h-screen text-slate-900">
    <div class="min-h-screen lg:flex" data-app-layout data-user-id="{{ auth()->id() }}">
        <button type="button" data-sidebar-backdrop hidden tabindex="-1" aria-label="Tutup navigasi"
            class="sidebar-backdrop"></button>
        <aside id="app-sidebar" data-sidebar aria-label="Navigasi utama"
            class="app-sidebar border-b border-slate-200 bg-white lg:min-h-screen lg:w-72 lg:shrink-0 lg:border-b-0 lg:border-r">
            <div class="flex h-16 items-center justify-between gap-3 border-b border-slate-200 px-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl border border-blue-200 bg-blue-50">
                        <span class="text-sm font-bold text-blue-700">
                            SW
                        </span>
                    </div>

                    <div>
                        <p class="font-semibold text-slate-900">
                            SignWork
                        </p>

                        <p class="text-xs text-slate-500">
                            Document Workflow
                        </p>
                    </div>
                </a>
                <button type="button" data-sidebar-close hidden aria-controls="app-sidebar" aria-label="Tutup navigasi"
                    class="sidebar-control inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-800 hover:bg-blue-100">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        class="h-5 w-5">
                        <path d="m14 6-6 6 6 6" />
                    </svg>
                </button>
            </div>

            <nav class="space-y-1 p-4">
                <a href="{{ route('dashboard') }}" @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                    'bg-blue-50 text-blue-800' => request()->routeIs('dashboard'),
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                        'dashboard'
                    )
                ])>
                    Dashboard
                </a>

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.documents.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-cyan-50 text-cyan-800' => request()->routeIs('admin.documents.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'admin.documents.*'
                        )
                    ])>
                        Semua Dokumen
                    </a>

                    <a href="{{ route('admin.users.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-indigo-50 text-indigo-800' => request()->routeIs('admin.users.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'admin.users.*'
                        )
                    ])>
                        Manajemen User
                    </a>

                    <a href="{{ route('admin.activity.index') }}"
                        class="block rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs('admin.activity.*') ? 'bg-blue-50 text-blue-800' : 'text-slate-600 hover:bg-slate-100' }}">Log
                        Aktivitas</a>
                @else
                    <a href="{{ route('documents.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-blue-50 text-blue-800' => request()->routeIs('documents.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'documents.*'
                        )
                    ])>
                        Dokumen Saya
                    </a>

                    <a href="{{ route('incoming-documents.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-cyan-50 text-cyan-800' => request()->routeIs('incoming-documents.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'incoming-documents.*'
                        )
                    ])>
                        Dokumen Masuk
                    </a>

                    <a href="{{ route('approvals.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-amber-50 text-amber-800' => request()->routeIs('approvals.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'approvals.*'
                        )
                    ])>
                        Verifikasi
                    </a>

                    <a href="{{ route('signatures.index') }}" @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                        'bg-violet-50 text-violet-800' => request()->routeIs('signatures.*'),
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !request()->routeIs(
                            'signatures.*'
                        )
                    ])>
                        Tanda Tangan
                    </a>

                    <div class="pt-5">
                        <p class="px-3 pb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Master</p>
                        <details class="group" @if(request()->routeIs('workflow-settings.*')) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-blue-900 hover:bg-blue-50 [&::-webkit-details-marker]:hidden">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 shadow-sm">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                        <circle cx="9" cy="8" r="3" />
                                        <path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 4v2" />
                                    </svg>
                                </span>
                                <span class="flex-1">Pengguna</span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 transition-transform group-open:rotate-180">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </summary>
                            <div class="ml-8 mt-2 space-y-1 border-l border-blue-100 pl-3">
                                @foreach (['signer' => 'Penandatangan', 'destination' => 'Tujuan Naskah', 'approver' => 'Verifikator'] as $masterType => $masterLabel)
                                    <a href="{{ route('workflow-settings.group', ['type' => $masterType]) }}" @if(request()->route('type') === $masterType || (isset($entry) && request()->routeIs('workflow-settings.entry.*') && $entry->type->value === $masterType)) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition hover:bg-blue-50 hover:text-blue-900 {{ request()->route('type') === $masterType ? 'bg-blue-100 text-blue-900' : 'text-slate-600' }}">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-300"></span>
                                        {{ $masterLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif
            </nav>
        </aside>

        <div data-app-content class="min-w-0 flex-1">
            <header class="app-header border-b border-slate-200 bg-white">
                <div class="flex min-h-16 items-center justify-between gap-4 px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-sidebar-toggle hidden aria-controls="app-sidebar"
                            aria-expanded="true" aria-label="Tutup navigasi"
                            class="sidebar-control inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-800 hover:bg-blue-100">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" class="h-5 w-5">
                                <path d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <p class="hidden text-sm text-slate-500 sm:block">
                            @if (auth()->user()->isAdmin())
                                Panel monitoring dan administrasi SignWork
                            @else
                                Workflow dokumen berbasis assignment per dokumen
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <details class="relative">
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2 text-right transition hover:bg-blue-50 [&::-webkit-details-marker]:hidden">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">{{ auth()->user()->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ auth()->user()->role->label() }} · Profil Saya</span>
                                </span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-slate-500">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </summary>
                            <div class="absolute right-0 z-30 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-lg">
                                <a href="{{ route('profile.show') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-blue-50">Lihat Profil</a>
                                <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-blue-50">Edit Profil</a>
                                <a href="{{ route('profile.password.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-blue-50">Ganti Password</a>
                            </div>
                        </details>

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf

                            <button type="submit"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="px-6 py-8 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
    @php
        $feedbackMessages = collect(['success', 'info', 'error'])
            ->filter(fn($type) => session()->has($type))
            ->map(fn($type) => ['type' => $type, 'message' => session($type)]);
        $feedbackErrors = $errors->all();
        $feedbackFailed = session()->has('error') || count($feedbackErrors) > 0;
        $feedbackTitle = $feedbackFailed ? 'Periksa kembali' : (session()->has('success') ? 'Berhasil' : 'Informasi');
    @endphp
    @if ($feedbackMessages->isNotEmpty() || count($feedbackErrors) > 0)
        <dialog open data-feedback-modal aria-labelledby="feedback-title" aria-describedby="feedback-messages"
            class="feedback-modal rounded-2xl border border-blue-200 bg-white p-0 text-slate-900 shadow-xl">
            <div class="border-b border-blue-100 {{ $feedbackFailed ? 'bg-rose-100' : 'bg-blue-50' }} px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">SignWork</p>
                <h2 id="feedback-title" class="mt-1 text-xl font-semibold">{{ $feedbackTitle }}</h2>
            </div>
            <div id="feedback-messages" class="space-y-3 px-6 py-5 text-sm leading-6">
                @foreach ($feedbackMessages as $feedback)
                    <p class="wrap-break-words">{{ $feedback['message'] }}</p>
                @endforeach
                @if (count($feedbackErrors) > 0)
                    <ul class="list-disc space-y-1 pl-5 text-rose-800">
                        @foreach ($feedbackErrors as $feedbackError)
                            <li class="wrap-break-words">{{ $feedbackError }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <form method="dialog" class="flex justify-end border-t border-blue-100 px-6 py-4">
                <button autofocus type="submit"
                    class="min-h-11 rounded-lg bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">OK,
                    mengerti</button>
            </form>
        </dialog>
    @endif
</body>

</html>
