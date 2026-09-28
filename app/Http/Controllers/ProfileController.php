<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'jabatan', 'unit_kerja', 'pangkat', 'golongan']));

        return to_route('profile.edit')->with('success', 'Profil berhasil diperbarui. Muat ulang halaman posisi QR untuk memakai data terbaru.');
    }
}
