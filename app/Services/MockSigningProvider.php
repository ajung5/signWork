<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class MockSigningProvider implements SigningProvider
{
    public function __construct(private PdfEngine $pdf) {}

    public function sign(string $input, string $output, string $transactionId, string $name, array $context = []): void
    {
        if (config('signwork.provider') !== 'mock') {
            throw ValidationException::withMessages(['provider' => 'Provider BSrE belum diimplementasikan. Tidak ada fallback diam-diam ke mock.']);
        }
        $this->pdf->run('mock_sign', $input, [
            ...$context,
            'output' => $this->pdf->path($output),
            'receipt' => $transactionId.' | '.$name.' | '.now()->toIso8601String(),
        ]);
    }
}
