<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Document;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('activity_user', $request->user()?->id);

        $response = $next($request);
        if ($request->hasSession()) {
            $fresh = $request->session()->get('_flash.new', []);
            $request->attributes->set('activity_errors', in_array('errors', $fresh, true) ? ($request->session()->get('errors')?->getBag('default')->keys() ?? []) : []);
            $request->attributes->set('activity_failed', in_array('error', $fresh, true));
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->route()?->getName() || ($request->isMethod('GET') && $response->getStatusCode() < 400)) {
            return;
        }
        try {
            $errors = $request->attributes->get('activity_errors', []);
            $failed = $response->getStatusCode() >= 400 || count($errors) > 0 || $request->attributes->get('activity_failed', false);
            $event = $request->route()->getName();
            $message = $failed ? 'Permintaan ditolak atau gagal. Periksa status respons dan validasi.' : 'Permintaan berhasil diproses.';
            if ($event === 'documents.sign') {
                $message = $failed ? (in_array('passphrase', $errors) ? 'Passphrase simulasi salah atau belum diisi.' : 'Signing gagal; tahap belum diselesaikan.') : 'Passphrase simulasi valid; signing berhasil.';
            } elseif ($event === 'documents.send' && ! $failed) {
                $message = 'Dokumen final dikirim ke penerima.';
            } elseif ($event === 'documents.pdf.store' && $failed) {
                $message = 'Penyimpanan dokumen gagal. Periksa format, ukuran, worker PDF, atau konversi Word.';
            }
            $message = $request->attributes->get('activity_message', $message);
            $document = $request->route('document');
            ActivityLog::create([
                'user_id' => $request->user()?->id ?? $request->attributes->get('activity_user'),
                'event' => $event, 'outcome' => $failed ? 'error' : 'success',
                'http_status' => $response->getStatusCode(),
                'document_uuid' => $document instanceof Document ? $document->uuid : null,
                'message' => $message,
            ]);
        } catch (\Throwable $error) {
            Log::warning('Activity log could not be stored.', ['exception' => get_class($error)]);
        }
    }
}
