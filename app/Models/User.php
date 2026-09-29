<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'nik', 'password', 'role', 'jabatan', 'unit_kerja', 'pangkat', 'golongan'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function documents(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'owner_id'
        );
    }

    public function destinationDocuments(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'destination_user_id'
        );
    }

    public function approvalDocuments(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'approver_id'
        );
    }

    public function signatureDocuments(): HasMany
    {
        return $this->hasMany(
            Document::class,
            'signer_id'
        );
    }

    public function workflowMasterEntries(): HasMany
    {
        return $this->hasMany(
            WorkflowMasterEntry::class
        );
    }

    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(UserRole::Superadmin);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
