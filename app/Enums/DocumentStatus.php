<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case WaitingApproval = 'waiting_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case WaitingSignature = 'waiting_signature';
    case Signing = 'signing';
    case Signed = 'signed';
    case SignFailed = 'sign_failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::WaitingApproval => 'Menunggu Verifikasi',
            self::Approved => 'Terverifikasi',
            self::Rejected => 'Ditolak',
            self::WaitingSignature => 'Menunggu Tanda Tangan',
            self::Signing => 'Proses Tanda Tangan',
            self::Signed => 'Ditandatangani',
            self::SignFailed => 'Tanda Tangan Gagal',
        };
    }
}
