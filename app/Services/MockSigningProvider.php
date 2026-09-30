<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class MockSigningProvider implements SigningProvider
{
    public function __construct(private PdfEngine $pdf) {}

    public function sign(string $input, string $output, string $transactionId, string $name, array $context = []): SigningResult
    {
        if (config('signwork.provider') !== 'mock') {
            throw ValidationException::withMessages(['provider' => 'Provider mock dipanggil saat konfigurasi bukan mock. Tidak ada fallback diam-diam.']);
        }
        $workerContext = $context;
        unset($workerContext['passphrase']);
        $this->pdf->run('mock_sign', $input, [
            ...$workerContext,
            'output' => $this->pdf->path($output),
            'receipt' => $transactionId.' | '.$name.' | '.now()->toIso8601String(),
        ]);

        return new SigningResult($transactionId);
    }
}
