<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $users = User::query()->withCount('documents')->orderBy('name')->paginate(15);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.users.create');
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $role = UserRole::tryFrom($request->string('role')->toString()) ?? UserRole::User;
        abort_unless($request->user()->isSuperadmin() || $role === UserRole::User, 403);
        $user = new User;

        $user->forceFill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'email_verified_at' => now(),
            'role' => $role,
        ]);

        $user->fill($request->safe()->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']));
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin($request);

        abort_if(
            $user->isAdmin() && ! $request->user()->isSuperadmin(),
            403,
            'Akun admin hanya dapat dikelola oleh Superadmin.'
        );

        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        abort_if(
            $user->isAdmin() && ! $request->user()->isSuperadmin(),
            403,
            'Akun admin hanya dapat dikelola oleh Superadmin.'
        );

        $role = UserRole::tryFrom($request->string('role')->toString()) ?? $user->role;
        abort_unless($request->user()->isSuperadmin() || $role === UserRole::User, 403);

        $data = [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'role' => $role,
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->fill($request->safe()->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']));
        $user->forceFill($data)->save();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($user->isAdmin() && ! $request->user()->isSuperadmin()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Akun admin hanya dapat dihapus oleh Superadmin.');
        }

        if ($user->isSuperadmin() && $user->id === $request->user()->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Akun Superadmin yang sedang digunakan tidak dapat dihapus.');
        }

        if ($this->hasDocumentReferences($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'User tidak dapat dihapus karena masih menjadi bagian dari riwayat dokumen.');
        }

        WorkflowMasterEntry::query()->where('user_id', $user->id)->orWhere('target_user_id', $user->id)->delete();

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dihapus. Data Master yang terkait otomatis dibersihkan.');
    }

    private function hasDocumentReferences(User $user): bool
    {
        return Document::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->where('owner_id', $user->id)
                    ->orWhere('destination_user_id', $user->id)
                    ->orWhere('approver_id', $user->id)
                    ->orWhere('signer_id', $user->id)
                    ->orWhereHas('cycles.approvals', fn ($query) => $query->where('user_id', $user->id))
                    ->orWhereHas('cycles.signatures', fn ($query) => $query->where('user_id', $user->id));
            })
            ->exists();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
