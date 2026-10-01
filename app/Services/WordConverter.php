<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class WordConverter
{
    public function convert(string $source): string
    {
        $disk = Storage::disk('local');
        $directory = 'signwork/conversion/'.Str::uuid();
        $disk->makeDirectory($directory.'/profile/user');
        $disk->put($directory.'/profile/user/registrymodifications.xcu', '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry"><item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop><prop oor:name="DisableMacrosExecution" oor:op="fuse"><value>true</value></prop></item><item oor:path="/org.openoffice.Office.Writer/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>0</value></prop></item></oor:items>');
        $output = dirname($source).'/'.Str::uuid().'.pdf';
        $timeout = max(1, (int) config('signwork.conversion_timeout', 120));
        $process = null;
        $startedAt = microtime(true);
        try {
            // The PHP built-in server defaults to max_execution_time=30. A
            // LibreOffice conversion can take longer than that on Windows.
            if (function_exists('set_time_limit')) {
                set_time_limit($timeout + 30);
            }

            $profile = $this->fileUri($disk->path($directory.'/profile'));
            $binary = $this->resolveBinary((string) config('signwork.libreoffice'));
            $process = new Process([
                $binary,
                '-env:UserInstallation='.$profile,
                '--headless',
                '--nologo',
                '--nodefault',
                '--norestore',
                '--nolockcheck',
                '--convert-to',
                'pdf:writer_pdf_Export',
                '--outdir',
                $disk->path($directory),
                $disk->path($source),
            ]);
            $process->setTimeout($timeout);
            Log::debug('Word conversion started.', [
                'extension' => strtolower(pathinfo($source, PATHINFO_EXTENSION)),
                'binary' => basename($binary),
                'timeout' => $timeout,
            ]);
            $process->run();
            $converted = $directory.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf';
            if (! $process->isSuccessful() || ! $disk->exists($converted) || $disk->size($converted) === 0 || ! $disk->move($converted, $output)) {
                throw new \RuntimeException('Conversion failed');
            }

            Log::debug('Word conversion completed.', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $output;
        } catch (\Throwable $error) {
            Log::warning('Word conversion failed.', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $error::class,
                'stderr' => $process ? substr(trim($process->getErrorOutput()), 0, 1000) : null,
            ]);
            $disk->delete($output);
            $message = 'Konversi Word gagal. Periksa LibreOffice, SIGNWORK_LIBREOFFICE, font, dan pastikan dokumen tidak diproteksi.';
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        } finally {
            $disk->deleteDirectory($directory);
        }
    }

    private function resolveBinary(string $configured): string
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! str_ends_with(strtolower($configured), 'soffice.exe')) {
            return $configured;
        }

        $consoleBinary = dirname($configured).DIRECTORY_SEPARATOR.'soffice.com';

        return is_file($consoleBinary) ? $consoleBinary : $configured;
    }

    private function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (preg_match('/^([A-Za-z]):\/(.*)$/', $normalized, $matches) === 1) {
            $segments = array_filter(explode('/', $matches[2]), static fn (string $segment): bool => $segment !== '');

            return 'file:///'.strtoupper($matches[1]).':/'.implode('/', array_map('rawurlencode', $segments));
        }

        $segments = array_filter(explode('/', ltrim($normalized, '/')), static fn (string $segment): bool => $segment !== '');

        return (str_starts_with($normalized, '/') ? 'file:///' : 'file://')
            .implode('/', array_map('rawurlencode', $segments));
    }
}
