<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class PdfEngine
{
    public function path(string $path): string
    {
        return Storage::disk('local')->path($path);
    }

    public function hash(string $path): string
    {
        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['pdf' => 'File PDF tidak tersedia.']);
        }

        return hash_file('sha256', $this->path($path));
    }

    public function ensureHash(string $path, string $expected): void
    {
        if (! hash_equals($expected, $this->hash($path))) {
            throw ValidationException::withMessages(['pdf' => 'Integritas PDF berubah. Proses dihentikan.']);
        }
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(string $action, string $input, array $payload = []): array
    {
        $process = new Process([(string) config('signwork.python'), resource_path('pdf/worker.py')]);
        $process->setTimeout((int) config('signwork.timeout'));
        $process->setInput(json_encode(['action' => $action, 'input' => $this->path($input), ...$payload], JSON_THROW_ON_ERROR));
        try {
            $process->run();
        } catch (ExceptionInterface $exception) {
            $message = 'PDF worker tidak tersedia atau timeout. Periksa SIGNWORK_PYTHON.';
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        }
        $result = json_decode($process->getOutput(), true);
        if (! $process->isSuccessful() || ! is_array($result) || isset($result['error'])) {
            $message = $result['error'] ?? 'PDF worker gagal. Periksa SIGNWORK_PYTHON dan requirements pada interpreter yang dipakai server.';
            if (str_contains($process->getErrorOutput(), 'ModuleNotFoundError')) {
                $message = 'Dependency Python belum tersedia pada interpreter server. Pasang resources/pdf/requirements.txt melalui SIGNWORK_PYTHON.';
            }
            Log::warning('PDF worker failed.', ['action' => $action, 'exit_code' => $process->getExitCode()]);
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        }

        return $result;
    }
}
