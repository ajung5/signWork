<?php

namespace App\Services;

final readonly class BsreSignResponse
{
    public function __construct(
        public ?string $pdf,
        public ?string $documentId,
    ) {}
}
