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
        $timeout = max(1, (int) config('signwork.timeout', 120));

        // The PHP built-in server defaults to max_execution_time=30. A PDF
        // scan/render can legitimately take longer than that on Windows,
        // especially for scanned or multi-page documents. Extend the PHP
        // request limit to at least the worker timeout so PHP does not kill
        // the request while the child process is still running.
        if (function_exists('set_time_limit')) {
            set_time_limit($timeout + 30);
        }

        $startedAt = microtime(true);
        $process = new Process([(string) config('signwork.python'), resource_path('pdf/worker.py')]);
        $process->setTimeout($timeout);
        $process->setInput(json_encode(['action' => $action, 'input' => $this->path($input), ...$payload], JSON_THROW_ON_ERROR));
        Log::debug('PDF worker started.', [
            'action' => $action,
            'timeout' => $timeout,
        ]);
        try {
            $process->run();
        } catch (ExceptionInterface $exception) {
            $message = 'PDF worker tidak tersedia atau timeout. Periksa SIGNWORK_PYTHON.';
            Log::warning('PDF worker exception.', [
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $exception::class,
            ]);
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        }
        $result = json_decode($process->getOutput(), true);
        if (! $process->isSuccessful() || ! is_array($result) || isset($result['error'])) {
            $message = $result['error'] ?? 'PDF worker gagal. Periksa SIGNWORK_PYTHON dan requirements pada interpreter yang dipakai server.';
            if (str_contains($process->getErrorOutput(), 'ModuleNotFoundError')) {
                $message = 'Dependency Python belum tersedia pada interpreter server. Pasang resources/pdf/requirements.txt melalui SIGNWORK_PYTHON.';
            }
            Log::warning('PDF worker failed.', [
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exit_code' => $process->getExitCode(),
                'stderr' => substr(trim($process->getErrorOutput()), 0, 1000),
            ]);
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        }

        Log::debug('PDF worker completed.', [
            'action' => $action,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $result;
    }
}
