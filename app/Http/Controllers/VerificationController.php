<?php

namespace App\Http\Controllers;

use App\Models\DocumentCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VerificationController extends Controller
{
    public function show(string $publicId): Response
    {
        $cycle = DocumentCycle::query()->where('public_id', $publicId)->whereNotNull('submitted_at')->with('signatures')->firstOrFail();

        return response()->view('documents.verify', compact('cycle'))->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function compare(Request $request, string $publicId): Response
    {
        $cycle = DocumentCycle::query()->where('public_id', $publicId)->where('status', 'signed')->with('signatures')->firstOrFail();
        $data = $request->validate(['sha256' => ['required', 'regex:/\A[a-fA-F0-9]{64}\z/']]);
        $match = hash_equals($cycle->final_sha256, strtolower($data['sha256']));

        return response()->view('documents.verify', compact('cycle', 'match'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
