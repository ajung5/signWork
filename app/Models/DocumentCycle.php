<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentCycle extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (DocumentCycle $cycle): void {
            $cycle->public_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['pdf_metadata' => 'array', 'positions_confirmed_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApprovalStep::class)->orderBy('sequence');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignatureStep::class)->orderBy('sequence');
    }
}
