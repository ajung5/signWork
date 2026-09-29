<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function editPassword(): View
    {
        return view('profile.password');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'nik', 'jabatan', 'unit_kerja', 'pangkat', 'golongan']));

        return to_route('profile.edit')->with('success', 'Profil berhasil diperbarui. Muat ulang halaman posisi QR untuk memakai data terbaru.');
    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => Hash::make($request->string('password')->toString())]);

        return to_route('profile.password.edit')->with('success', 'Password berhasil diubah.');
    }
}
