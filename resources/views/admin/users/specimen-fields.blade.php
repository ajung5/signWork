<fieldset class="space-y-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
    <legend class="px-2 text-sm font-semibold text-blue-900">Identitas spesimen TTE</legend>
    <p class="text-xs text-slate-600">Lengkapi semua kolom untuk memakai format berbingkai. Nama lengkap beserta gelar diambil dari nama pengguna.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (['jabatan' => ['Jabatan', 255, 'Pranata Komputer Ahli Pertama'], 'unit_kerja' => ['OPD / Unit kerja', 255, 'Dinas Komunikasi dan Informatika'], 'pangkat' => ['Pangkat', 100, 'Penata Muda Tk. I'], 'golongan' => ['Golongan', 30, 'III/b']] as $key => [$label, $length, $example])
            <label class="block text-sm font-medium text-slate-700" for="{{ $key }}">{{ $label }}
                <input id="{{ $key }}" name="{{ $key }}" type="text" maxlength="{{ $length }}" value="{{ old($key, isset($user) ? $user->$key : '') }}" placeholder="{{ $example }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm">
                @error($key)<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror
            </label>
        @endforeach
    </div>
</fieldset>
