@extends('layouts.app')

@section('title', 'Edit Data Master - SignWork')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div>
            <p class="text-sm font-medium text-emerald-700">
                Data Master User
            </p>

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                Edit Data Master
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Ubah grup, user yang dipilih, atau jadikan entri ini sebagai default.
            </p>
        </div>

        <form action="{{ route('workflow-settings.entry.update', $entry) }}" method="POST" data-master-edit
            data-assigned-users="{{ json_encode($assignedUsers) }}"
            class="mt-7 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PUT')

            <div class="space-y-6 p-5 sm:p-6">
                <div>
                    <label for="target_user_id" class="block text-sm font-medium text-slate-700">
                        Grup
                    </label>

                    <fieldset class="mt-2 grid gap-3 sm:grid-cols-3" data-master-types>
                        <legend class="sr-only">Grup Data Master</legend>
                        @foreach ($types as $type)
                            <label
                                class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm font-medium text-blue-900">
                                <input type="radio" name="type" value="{{ $type->value }}" required
                                    @checked(old('type', $entry->type->value) === $type->value)>
                                {{ $type->label() }}
                            </label>
                        @endforeach
                    </fieldset>
                </div>

                <div>
                    <label for="target_user_id" class="block text-sm font-medium text-slate-700">
                        User
                    </label>

                    <select id="target_user_id" name="target_user_id" required
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">
                        @php($usedIds = $assignedUsers->get(old('type', $entry->type->value), collect()))
                        <option value="">Pilih user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @disabled($usedIds->contains($user->id))
                                @if ($usedIds->contains($user->id)) hidden @endif @selected(old('target_user_id', $entry->target_user_id) == $user->id)>
                                {{ $user->name }} — {{ $user->email }}
                                @if ($user->id === auth()->id())
                                    (Saya)
                                @endif
                            </option>
                        @endforeach
                    </select>

                    @error('target_user_id')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $entry->is_default))
                        class="mt-0.5 h-4 w-4 rounded border-slate-300">

                    <span>
                        <span class="block text-sm font-medium text-slate-800">
                            Jadikan default
                        </span>

                        <span class="mt-1 block text-xs leading-5 text-slate-500">
                            Hanya satu entri default yang digunakan otomatis untuk setiap kategori.
                        </span>
                    </span>
                </label>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                <a href="{{ route('workflow-settings.group', ['type' => $entry->type->value]) }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    Batal
                </a>

                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
