<?php

namespace App\Models;

use App\Enums\WorkflowMasterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowMasterEntry extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'target_user_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkflowMasterType::class,
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'target_user_id'
        );
    }
}
