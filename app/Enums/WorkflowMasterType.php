<?php

namespace App\Enums;

enum WorkflowMasterType: string
{
    case Destination = 'destination';
    case Approver = 'approver';
    case Signer = 'signer';

    public function label(): string
    {
        return match ($this) {
            self::Destination => 'Tujuan',
            self::Approver => 'Verifikator',
            self::Signer => 'Tanda Tangan',
        };
    }
}
