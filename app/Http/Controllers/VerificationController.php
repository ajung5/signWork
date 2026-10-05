<?php

namespace App\Http\Controllers;

use App\Models\DocumentCycle;
use App\Services\PdfEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function __construct(private readonly PdfEngine $pdf) {}

    public function index(): Response
    {
        return response()->view('documents.validation');
    }

    public function lookup(Request $request): Response|RedirectResponse
    {
        if ($request->hasFile('document')) {
            $data = $request->validate([
                'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            ]);

            $file = $data['document'];
            $storedPath = $file->storeAs('validation', Str::uuid().'.pdf');

            try {
                $inspection = $this->pdf->run('certificate_info', $storedPath);
                $fileSize = Storage::disk('local')->size($storedPath);
                $fileHash = hash_file(
                    'sha256',
                    Storage::disk('local')->path($storedPath)
                );
            } catch (ValidationException $exception) {
                return back()->withErrors([
                    'document' => data_get(
                        $exception->errors(),
                        'pdf.0',
                        'File PDF tidak dapat diperiksa.'
                    ),
                ])->withInput();
            } finally {
                Storage::disk('local')->delete($storedPath);
            }

            return response()->view('documents.validation', [
                'inspection' => $inspection,
                'fileName' => $file->getClientOriginalName(),
                'fileSize' => $fileSize,
                'fileHash' => $fileHash,
            ]);
        }

        // Kompatibilitas untuk tautan validasi lama.
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:500'],
        ]);

        $reference = trim($data['reference']);

        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            $reference = trim((string) parse_url($reference, PHP_URL_PATH), '/');
            $reference = Str::afterLast($reference, '/');
        }

        if (! Str::isUuid($reference)) {
            throw ValidationException::withMessages([
                'reference' => 'Masukkan ID validasi dokumen yang sah.',
            ]);
        }

        return to_route('verification.show', $reference);
    }

    public function show(string $publicId): Response
    {
        $cycle = DocumentCycle::query()
            ->where('public_id', $publicId)
            ->whereNotNull('submitted_at')
            ->with('signatures.user')
            ->firstOrFail();

        return $this->validationResponse($cycle);
    }

    public function compare(Request $request, string $publicId): Response
    {
        $cycle = DocumentCycle::query()
            ->where('public_id', $publicId)
            ->where('status', 'signed')
            ->with('signatures.user')
            ->firstOrFail();
        $data = $request->validate(['sha256' => ['required', 'regex:/\A[a-fA-F0-9]{64}\z/']]);
        $match = hash_equals($cycle->final_sha256, strtolower($data['sha256']));

        return $this->validationResponse($cycle, $match);
    }

    private function validationResponse(DocumentCycle $cycle, ?bool $match = null): Response
    {
        $filePath = $cycle->current_path ?: $cycle->original_path;
        $fileSize = $filePath && Storage::disk('local')->exists($filePath)
            ? Storage::disk('local')->size($filePath)
            : null;
        $pageCount = count(data_get($cycle->pdf_metadata, 'pages', []));
        $certificateMetadata = [];
        if ($filePath && $cycle->status === 'signed' && config('signwork.provider') === 'bsre') {
            try {
                $certificateMetadata = $this->pdf->run('certificate_info', $filePath)['signatures'] ?? [];
            } catch (ValidationException) {
                // Certificate details are supplemental; validation must remain available
                // even when the server cannot parse an embedded CMS certificate.
                $certificateMetadata = [];
            }
        }
        $certificateByStep = [];
        foreach ($cycle->signatures as $index => $step) {
            $certificateByStep[$step->id] = $certificateMetadata[$index] ?? [];
        }
        $viewData = compact('cycle', 'fileSize', 'pageCount', 'certificateByStep');
        if ($match !== null) {
            $viewData['match'] = $match;
        }

        return response()
            ->view('documents.verify', $viewData)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
