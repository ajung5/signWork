<?php

namespace App\Http\Controllers;

use App\Models\DocumentCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function index(): Response
    {
        return response()->view('documents.validation');
    }

    public function lookup(Request $request): RedirectResponse
    {
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
                'reference' => 'Masukkan tautan QR atau ID validasi dokumen yang sah.',
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
        $viewData = compact('cycle', 'fileSize', 'pageCount');
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
