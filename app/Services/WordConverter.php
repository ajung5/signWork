<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
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
        $conversionRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'signwork-conversion-'.Str::uuid();
        $profileRoot = $conversionRoot.DIRECTORY_SEPARATOR.'profile';
        $outputRoot = $conversionRoot.DIRECTORY_SEPARATOR.'output';
        $output = dirname($source).'/'.Str::uuid().'.pdf';
        $timeout = max(1, (int) config('signwork.conversion_timeout', 120));
        $process = null;
        $startedAt = microtime(true);

        try {
            $sourcePhysicalPath = $disk->path($source);

            if (! is_file($sourcePhysicalPath)) {
                throw new \RuntimeException('Word source file does not exist');
            }

            if (! mkdir($profileRoot.'/user', 0755, true) && ! is_dir($profileRoot.'/user')) {
                throw new \RuntimeException('LibreOffice profile directory could not be created');
            }

            if (! mkdir($outputRoot, 0755, true) && ! is_dir($outputRoot)) {
                throw new \RuntimeException('LibreOffice output directory could not be created');
            }

            file_put_contents(
                $profileRoot.'/user/registrymodifications.xcu',
                '<?xml version="1.0"?>
<oor:items xmlns:oor="http://openoffice.org/2001/registry">
    <item oor:path="/org.openoffice.Office.Common/Security/Scripting">
        <prop oor:name="MacroSecurityLevel" oor:op="fuse">
            <value>3</value>
        </prop>
        <prop oor:name="DisableMacrosExecution" oor:op="fuse">
            <value>true</value>
        </prop>
    </item>
    <item oor:path="/org.openoffice.Office.Writer/Content/Update">
        <prop oor:name="Link" oor:op="fuse">
            <value>0</value>
        </prop>
    </item>
</oor:items>'
            );

            if (function_exists('set_time_limit')) {
                set_time_limit($timeout + 30);
            }

            $profile = $this->fileUri($profileRoot);
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
                $outputRoot,
                $sourcePhysicalPath,
            ]);

            $process->setTimeout($timeout);

            Log::debug('Word conversion started.', [
                'extension' => strtolower(pathinfo($source, PATHINFO_EXTENSION)),
                'binary' => basename($binary),
                'timeout' => $timeout,
            ]);

            $process->run();

            $expected = $outputRoot
                .DIRECTORY_SEPARATOR
                .pathinfo($sourcePhysicalPath, PATHINFO_FILENAME)
                .'.pdf';

            $converted = $this->waitForConvertedPdf($outputRoot, $expected);

            Log::debug('Word conversion process finished.', [
                'exit_code' => $process->getExitCode(),
                'successful' => $process->isSuccessful(),
                'stdout' => substr(trim($process->getOutput()), 0, 1000),
                'stderr' => substr(trim($process->getErrorOutput()), 0, 1000),
                'expected_output' => $expected,
                'output_directory' => $outputRoot,
                'generated_pdfs' => $this->generatedPdfs($outputRoot),
            ]);

            if (! $process->isSuccessful() || $converted === null) {
                throw new \RuntimeException('Conversion did not produce a readable PDF');
            }

            $contents = file_get_contents($converted);

            if ($contents === false || $contents === '') {
                throw new \RuntimeException('Converted PDF could not be read');
            }

            if (! $disk->put($output, $contents)) {
                throw new \RuntimeException('Converted PDF could not be stored in the document directory');
            }

            Log::debug('Word conversion completed.', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $output;
        } catch (\Throwable $error) {
            Log::warning('Word conversion failed.', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $error::class,
                'stderr' => $process
                    ? substr(trim($process->getErrorOutput()), 0, 1000)
                    : null,
            ]);

            $disk->delete($output);

            $message = 'Konversi Word gagal. Periksa LibreOffice, SIGNWORK_LIBREOFFICE, font, dan pastikan dokumen tidak diproteksi.';

            request()->attributes->set('activity_message', $message);

            throw ValidationException::withMessages([
                'pdf' => $message,
            ]);
        } finally {
            if (is_dir($conversionRoot)) {
                File::deleteDirectory($conversionRoot);
            }
        }
    }

    private function resolveBinary(string $configured): string
    {
        if (
            PHP_OS_FAMILY !== 'Windows'
            || ! str_ends_with(strtolower($configured), 'soffice.exe')
        ) {
            return $configured;
        }

        $consoleBinary = dirname($configured)
            .DIRECTORY_SEPARATOR
            .'soffice.com';

        return is_file($consoleBinary)
            ? $consoleBinary
            : $configured;
    }

    private function fileUri(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (preg_match('/^([A-Za-z]):\/(.*)$/', $normalized, $matches) === 1) {
            $segments = array_filter(
                explode('/', $matches[2]),
                static fn (string $segment): bool => $segment !== ''
            );

            return 'file:///'
                .strtoupper($matches[1])
                .':/'
                .implode('/', array_map('rawurlencode', $segments));
        }

        $segments = array_filter(
            explode('/', ltrim($normalized, '/')),
            static fn (string $segment): bool => $segment !== ''
        );

        return (str_starts_with($normalized, '/') ? 'file:///' : 'file://')
            .implode('/', array_map('rawurlencode', $segments));
    }

    private function findConvertedPdf(
        string $directory,
        string $expected
    ): ?string {
        if ($this->isReadablePdf($expected)) {
            return $expected;
        }

        $candidates = glob($directory.DIRECTORY_SEPARATOR.'*.pdf') ?: [];

        foreach ($candidates as $candidate) {
            if ($this->isReadablePdf($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function waitForConvertedPdf(
        string $directory,
        string $expected
    ): ?string {
        $deadline = microtime(true) + 15;

        do {
            $converted = $this->findConvertedPdf($directory, $expected);

            if ($converted !== null) {
                return $converted;
            }

            usleep(250000);
        } while (microtime(true) < $deadline);

        return null;
    }

    private function isReadablePdf(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $size = filesize($path);

        return $size !== false && $size > 0;
    }

    /** @return list<string> */
    private function generatedPdfs(string $directory): array
    {
        return array_values(array_map(
            'basename',
            glob($directory.DIRECTORY_SEPARATOR.'*.pdf') ?: []
        ));
    }
}
