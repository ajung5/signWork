<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - SignWork</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-white text-slate-900">
    <main class="min-h-screen lg:grid lg:grid-cols-2">
        <section
            class="relative hidden min-h-screen overflow-hidden bg-slate-950 lg:flex lg:flex-col"
            aria-label="Informasi SignWork"
        >
            <div
                class="absolute inset-0"
                style="background:
                    radial-gradient(circle at 22% 20%, rgba(37, 99, 235, 0.28), transparent 28%),
                    radial-gradient(circle at 78% 68%, rgba(6, 182, 212, 0.18), transparent 32%),
                    linear-gradient(145deg, #0f172a 0%, #111c3f 54%, #172554 100%);"
            ></div>

            <div class="relative z-10 flex h-full min-h-screen flex-col px-12 py-10 xl:px-16">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-400/30 bg-cyan-400/10">
                        <svg
                            class="h-5 w-5 text-cyan-300"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path d="M12 3 19 6v5c0 4.7-2.9 8.5-7 10-4.1-1.5-7-5.3-7-10V6l7-3Z" />
                            <path d="m9 12 2 2 4-5" />
                        </svg>
                    </div>

                    <div>
                        <p class="text-lg font-semibold text-slate-100">
                            SignWork
                        </p>

                        <p class="text-xs tracking-wide text-slate-400">
                            DOCUMENT WORKFLOW PORTAL
                        </p>
                    </div>
                </div>

                <div class="my-auto">
                    <div class="relative mx-auto max-w-xl">
                        <svg
                            class="mx-auto w-full max-w-lg"
                            viewBox="0 0 640 440"
                            role="img"
                            aria-label="Ilustrasi workflow dokumen digital"
                        >
                            <defs>
                                <linearGradient id="flow-line" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#2563eb" />
                                    <stop offset="100%" stop-color="#22d3ee" />
                                </linearGradient>

                                <linearGradient id="doc-fill" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#eff6ff" />
                                    <stop offset="100%" stop-color="#bfdbfe" />
                                </linearGradient>

                                <filter id="soft-glow">
                                    <feGaussianBlur stdDeviation="7" result="blur" />
                                    <feMerge>
                                        <feMergeNode in="blur" />
                                        <feMergeNode in="SourceGraphic" />
                                    </feMerge>
                                </filter>
                            </defs>

                            <ellipse
                                cx="320"
                                cy="226"
                                rx="220"
                                ry="125"
                                fill="none"
                                stroke="url(#flow-line)"
                                stroke-width="18"
                                stroke-linecap="round"
                                opacity="0.9"
                            />

                            <path
                                d="M498 294 550 298 521 343"
                                fill="none"
                                stroke="#22d3ee"
                                stroke-width="18"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M142 153 92 147 118 105"
                                fill="none"
                                stroke="#2563eb"
                                stroke-width="18"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <g transform="translate(76 66) rotate(-9 84 105)">
                                <rect
                                    x="18"
                                    y="14"
                                    width="140"
                                    height="194"
                                    rx="12"
                                    fill="url(#doc-fill)"
                                    stroke="#60a5fa"
                                    stroke-width="3"
                                />
                                <path d="M52 54h70" stroke="#334155" stroke-width="8" stroke-linecap="round" />
                                <path d="M52 79h78" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <path d="M52 99h62" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <path d="M52 119h72" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <circle cx="123" cy="161" r="22" fill="#f59e0b" opacity="0.92" />
                                <circle cx="123" cy="161" r="12" fill="#fef3c7" />
                            </g>

                            <g transform="translate(418 60) rotate(8 72 100)">
                                <rect
                                    x="10"
                                    y="10"
                                    width="132"
                                    height="184"
                                    rx="12"
                                    fill="url(#doc-fill)"
                                    stroke="#60a5fa"
                                    stroke-width="3"
                                />
                                <path d="M40 49h67" stroke="#334155" stroke-width="8" stroke-linecap="round" />
                                <path d="M40 74h70" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <path d="M40 94h55" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <path d="M40 114h66" stroke="#93c5fd" stroke-width="7" stroke-linecap="round" />
                                <circle cx="107" cy="151" r="21" fill="#ef4444" opacity="0.88" />
                                <circle cx="107" cy="151" r="11" fill="#fee2e2" />
                            </g>

                            <g filter="url(#soft-glow)">
                                <path
                                    d="M320 132 386 158v61c0 60-34 102-66 117-32-15-66-57-66-117v-61l66-26Z"
                                    fill="#e0f2fe"
                                    stroke="#38bdf8"
                                    stroke-width="5"
                                />
                                <path
                                    d="m291 225 20 20 42-55"
                                    fill="none"
                                    stroke="#2563eb"
                                    stroke-width="14"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </g>

                            <circle cx="203" cy="278" r="29" fill="#22d3ee" opacity="0.95" />
                            <path
                                d="m190 278 9 9 17-20"
                                fill="none"
                                stroke="#0f172a"
                                stroke-width="7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <circle cx="455" cy="287" r="26" fill="#60a5fa" opacity="0.92" />
                            <path
                                d="M455 272v17l10 7"
                                fill="none"
                                stroke="#0f172a"
                                stroke-width="6"
                                stroke-linecap="round"
                            />

                            <rect x="238" y="60" width="44" height="44" rx="8" fill="#2563eb" opacity="0.9" />
                            <path d="m251 82 8 8 13-17" fill="none" stroke="#dbeafe" stroke-width="5" />

                            <path d="m516 204 24 38h-48l24-38Z" fill="#22d3ee" opacity="0.95" />
                            <circle cx="104" cy="260" r="11" fill="#22d3ee" />
                            <circle cx="561" cy="161" r="9" fill="#60a5fa" />
                            <rect x="181" y="354" width="36" height="20" rx="6" fill="#2563eb" opacity="0.8" />
                            <ellipse cx="383" cy="369" rx="21" ry="13" fill="#0ea5e9" opacity="0.85" />
                        </svg>

                        <div class="-mt-6 max-w-xl">
                            <h1 class="text-4xl font-semibold tracking-tight text-slate-100 xl:text-5xl">
                                Workflow dokumen digital yang lebih terstruktur.
                            </h1>

                            <p class="mt-4 max-w-lg text-base leading-7 text-slate-300">
                                Kelola pengajuan, verifikasi, dan proses tanda tangan elektronik
                                melalui satu alur kerja yang terkontrol.
                            </p>

                            <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5">
                                <span class="h-2 w-2 rounded-full bg-cyan-300"></span>

                                <span class="text-xs font-medium text-cyan-100">
                                    Workflow Terintegrasi
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-700/70 pt-4">
                    <p class="text-xs text-slate-500">
                        Akses hanya diperuntukkan bagi pengguna SignWork yang terdaftar.
                    </p>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-white px-6 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50">
                    <svg
                        class="h-7 w-7 text-blue-600"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path d="M12 3 19 6v5c0 4.7-2.9 8.5-7 10-4.1-1.5-7-5.3-7-10V6l7-3Z" />
                        <path d="m9 12 2 2 4-5" />
                    </svg>
                </div>

                <div class="mt-6 text-center">
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
                        Masuk ke SignWork
                    </h2>

                    <p class="mt-2 text-sm text-slate-500">
                        Gunakan email dan password akun yang terdaftar.
                    </p>
                </div>

                <form
                    action="{{ route('login.store') }}"
                    method="POST"
                    class="mt-8 space-y-5"
                >
                    @csrf

                    <div>
                        <label
                            for="email"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Email
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            required
                            autofocus
                            placeholder="nama@instansi.go.id"
                            class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                        >

                        @error('email')
                            <p class="mt-2 text-sm text-red-700">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label
                            for="password"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Password
                        </label>

                        <div class="relative mt-2">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                                placeholder="Masukkan password"
                                class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-3 pr-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                            >

                            <button
                                type="button"
                                data-password-toggle
                                aria-controls="password"
                                aria-label="Tampilkan password"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-500 transition hover:text-slate-800"
                            >
                                <svg
                                    data-eye-open
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                    <circle cx="12" cy="12" r="2.5" />
                                </svg>

                                <svg
                                    data-eye-closed
                                    class="hidden h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path d="M3 3 21 21" />
                                    <path d="M10.6 6.2A10.9 10.9 0 0 1 12 6c6 0 9.5 6 9.5 6a16.1 16.1 0 0 1-3 3.7" />
                                    <path d="M6.3 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a10.8 10.8 0 0 0 3-.4" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-3">
                        <input
                            name="remember"
                            type="checkbox"
                            value="1"
                            @checked(old('remember'))
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        >

                        <span class="text-sm text-slate-600">
                            Ingat saya
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-lg border border-blue-700 bg-blue-600 px-5 py-3 text-sm font-semibold text-blue-50 shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    >
                        Masuk ke SignWork
                    </button>
                </form>

                <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs leading-5 text-slate-500">
                        Jika Anda tidak dapat masuk, pastikan email dan password sesuai akun
                        SignWork yang telah terdaftar.
                    </p>
                </div>

                <p class="mt-10 text-center text-xs text-slate-400">
                    SignWork — Verifikasi Dokumen & Tanda Tangan Elektronik
                </p>
            </div>
        </section>
    </main>
</body>

</html>
