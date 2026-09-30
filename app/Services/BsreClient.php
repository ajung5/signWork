<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BsreClient
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function signPdf(string $path, string $nik, string $passphrase, ?string $linkQr = null): BsreSignResponse
    {
        if (! filled($this->config['base_url'] ?? null)) {
            throw ValidationException::withMessages(['provider' => 'SIGNWORK_BSRE_BASE_URL belum dikonfigurasi.']);
        }
        if (! $this->hasBasicAuth() && ! filled($this->config['bearer_token'] ?? null)) {
            throw ValidationException::withMessages(['provider' => 'Kredensial BSrE belum dikonfigurasi.']);
        }
        $file = fopen($path, 'rb');
        if ($file === false) {
            throw ValidationException::withMessages(['provider' => 'File PDF untuk BSrE tidak dapat dibaca.']);
        }

        try {
            $tampilan = strtolower(trim((string) ($this->config['tampilan'] ?? 'invisible')));
            $fields = [
                'nik' => $nik,
                'passphrase' => $passphrase,
                'tampilan' => $tampilan,
            ];
            if ($tampilan === 'visible') {
                $image = (bool) ($this->config['image'] ?? false);
                if ($image) {
                    throw ValidationException::withMessages([
                        'provider' => 'Mode visible dengan image=true belum didukung oleh adapter SignWork. Gunakan image=false atau mode invisible.',
                    ]);
                }
                if (! filled($linkQr)) {
                    throw ValidationException::withMessages([
                        'provider' => 'URL verifikasi dinamis untuk QR BSrE belum tersedia pada mode visible.',
                    ]);
                }
                $fields += [
                    'image' => 'false',
                    'linkQR' => (string) $linkQr,
                    'xAxis' => (string) ($this->config['x_axis'] ?? 3),
                    'yAxis' => (string) ($this->config['y_axis'] ?? 4),
                    'width' => (string) ($this->config['width'] ?? 80),
                    'height' => (string) ($this->config['height'] ?? 80),
                ];
            }
            if (filled($this->config['response_type'] ?? null)) {
                $fields['jenis_response'] = (string) $this->config['response_type'];
            }

            $response = $this->request()
                ->attach('file', $file, basename($path))
                ->post($this->url((string) ($this->config['sign_path'] ?? '/api/sign/pdf')), $fields);

            return $this->parseResponse($response, null);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $status = $response?->status();
            Log::warning('BSrE signing rejected', [
                'status' => $status,
                'content_type' => $response?->header('Content-Type'),
                'body' => $response ? mb_substr($response->body(), 0, 4000) : null,
            ]);
            throw ValidationException::withMessages([
                'provider' => $status
                    ? "BSrE menolak permintaan signing (HTTP {$status})."
                    : 'BSrE tidak dapat dihubungi dari server aplikasi.',
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'provider' => 'BSrE tidak dapat dihubungi dari server aplikasi.',
            ]);
        } finally {
            fclose($file);
        }
    }

    public function download(string $documentId): BsreSignResponse
    {
        try {
            $path = str_replace(
                '{id}',
                rawurlencode($documentId),
                (string) ($this->config['download_path'] ?? '/api/sign/download/{id}')
            );
            return $this->parseResponse($this->request()->get($this->url($path)), $documentId);
        } catch (RequestException $exception) {
            $status = $exception->response?->status();
            throw ValidationException::withMessages([
                'provider' => $status
                    ? "Dokumen hasil signing BSrE tidak dapat diunduh (HTTP {$status})."
                    : 'Dokumen hasil signing BSrE tidak dapat diunduh dari server aplikasi.',
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                'provider' => 'Dokumen hasil signing BSrE tidak dapat diunduh dari server aplikasi.',
            ]);
        }
    }

    private function request(): PendingRequest
    {
        $headers = ['Accept' => 'application/pdf, application/json'];
        $basic = $this->hasBasicAuth()
            ? 'Basic '.base64_encode(sprintf('%s:%s', $this->config['basic_username'], $this->config['basic_password']))
            : null;
        $bearer = filled($this->config['bearer_token'] ?? null)
            ? 'Bearer '.(string) $this->config['bearer_token']
            : null;
        if ($basic !== null && $bearer !== null) {
            // The BSrE collection sends both Authorization values on the same request.
            $headers['Authorization'] = [$basic, $bearer];
        } elseif ($basic !== null || $bearer !== null) {
            $headers['Authorization'] = $basic ?? $bearer;
        }

        return Http::timeout((int) ($this->config['timeout'] ?? 120))->withHeaders($headers);
    }

    private function hasBasicAuth(): bool
    {
        return filled($this->config['basic_username'] ?? null) && filled($this->config['basic_password'] ?? null);
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/').'/'.ltrim($path, '/');
    }

    private function parseResponse(Response $response, ?string $fallbackId): BsreSignResponse
    {
        $response->throw();
        $contentType = strtolower((string) $response->header('Content-Type'));
        $body = $response->body();
        $documentId = $this->documentId($response->headers(), null) ?? $fallbackId;
        if (str_contains($contentType, 'pdf') || str_starts_with($body, '%PDF-')) {
            return new BsreSignResponse($body, $documentId);
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            throw ValidationException::withMessages(['provider' => 'Respons BSrE tidak dikenali.']);
        }
        $documentId = $this->documentId($response->headers(), $json) ?? $fallbackId;
        $base64 = $this->findBase64($json);
        if ($base64 !== null) {
            $decoded = base64_decode($base64, true);
            if ($decoded === false || ! str_starts_with($decoded, '%PDF-')) {
                throw ValidationException::withMessages(['provider' => 'BSrE mengembalikan Base64 yang bukan PDF.']);
            }
            return new BsreSignResponse($decoded, $documentId);
        }

        return new BsreSignResponse(null, $documentId);
    }

    /** @param array<string, array<int, string>> $headers @param array<string, mixed>|null $json */
    private function documentId(array $headers, ?array $json): ?string
    {
        foreach (['id_dokumen', 'id-document', 'document-id', 'x-document-id'] as $key) {
            foreach ($headers as $header => $values) {
                if (strtolower($header) === $key && filled($values[0] ?? null)) {
                    return (string) $values[0];
                }
            }
        }
        if ($json === null) {
            return null;
        }
        foreach (['id_dokumen', 'id_document', 'document_id', 'id'] as $key) {
            $value = data_get($json, $key) ?? data_get($json, 'data.'.$key) ?? data_get($json, 'data.0.'.$key);
            if (filled($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $json */
    private function findBase64(array $json): ?string
    {
        foreach (['file', 'signed_file', 'document', 'base64', 'data'] as $key) {
            $value = data_get($json, $key);
            if (is_string($value) && $this->looksLikeBase64Pdf($value)) {
                return preg_replace('/^data:application\/pdf;base64,/', '', $value) ?: $value;
            }
            if (is_array($value)) {
                $nested = $this->findBase64($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function looksLikeBase64Pdf(string $value): bool
    {
        $candidate = preg_replace('/^data:application\/pdf;base64,/', '', $value) ?: $value;
        $decoded = base64_decode($candidate, true);

        return $decoded !== false && str_starts_with($decoded, '%PDF-');
    }
}
