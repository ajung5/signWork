<?php

namespace App\Services;

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
        try {
            $profile = 'file://'.str_replace('%2F', '/', rawurlencode($disk->path($directory.'/profile')));
            $process = new Process([(string) config('signwork.libreoffice'), '-env:UserInstallation='.$profile, '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $disk->path($directory), $disk->path($source)]);
            $process->setTimeout((int) config('signwork.conversion_timeout'));
            $process->run();
            $converted = $directory.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf';
            if (! $process->isSuccessful() || ! $disk->exists($converted) || $disk->size($converted) === 0 || ! $disk->move($converted, $output)) {
                throw new \RuntimeException('Conversion failed');
            }

            return $output;
        } catch (\Throwable $error) {
            $disk->delete($output);
            $message = 'Konversi Word gagal. Periksa LibreOffice, SIGNWORK_LIBREOFFICE, font, dan pastikan dokumen tidak diproteksi.';
            request()->attributes->set('activity_message', $message);
            throw ValidationException::withMessages(['pdf' => $message]);
        } finally {
            $disk->deleteDirectory($directory);
        }
    }
}
