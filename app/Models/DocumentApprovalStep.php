<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApprovalStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(DocumentCycle::class, 'document_cycle_id');
    }
}
