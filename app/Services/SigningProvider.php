<?php

namespace App\Services;

interface SigningProvider
{
    /** @param array<string, mixed> $context */
    public function sign(
        string $input,
        string $output,
        string $transactionId,
        string $name,
        array $context = []
    ): SigningResult;
}
