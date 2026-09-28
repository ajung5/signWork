<?php

namespace App\Services;

interface SigningProvider
{
    public function sign(string $input, string $output, string $transactionId, string $name, array $context = []): void;
}
