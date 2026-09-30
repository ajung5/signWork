<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BsreSigningProvider implements SigningProvider
{
    public function __construct(private readonly BsreClient $client) {}

    public function sign(string $input, string $output, string $transactionId, string $name, array $context = []): SigningResult
    {
        $nik = trim((string) ($context['nik'] ?? ''));
        $passphrase = (string) ($context['passphrase'] ?? '');
        if ($nik === '' || $passphrase === '') {
            throw ValidationException::withMessages(['provider' => 'NIK dan passphrase BSrE wajib tersedia.']);
        }

        $response = $this->client->signPdf(
            path: Storage::disk('local')->path($input),
            nik: $nik,
            passphrase: $passphrase,
        );
        $pdf = $response->pdf;
        if ($pdf === null && $response->documentId !== null) {
            $pdf = $this->client->download($response->documentId)->pdf;
        }
        if ($pdf === null || ! str_starts_with($pdf, '%PDF-')) {
            throw ValidationException::withMessages(['provider' => 'BSrE tidak mengembalikan PDF hasil signing.']);
        }

        Storage::disk('local')->put($output, $pdf);

        return new SigningResult($response->documentId ?: $transactionId);
    }
}
