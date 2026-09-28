<?php

namespace App\Models;

class DocumentSignatureStep extends DocumentApprovalStep
{
    protected function casts(): array
    {
        return [
            'profile_snapshot' => 'array', 'acted_at' => 'datetime', 'page' => 'integer',
            'specimen_scope' => 'string',
            'specimen_pages' => 'array',
            'x' => 'float', 'y' => 'float', 'width' => 'float', 'height' => 'float',
        ];
    }
}
