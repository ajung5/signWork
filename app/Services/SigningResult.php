<?php

namespace App\Services;

final readonly class SigningResult
{
    public function __construct(public string $providerTransactionId) {}
}
