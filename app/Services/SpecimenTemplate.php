<?php

namespace App\Services;

use App\Models\DocumentCycle;
use App\Models\User;

class SpecimenTemplate
{
    public const QR_SIZE = 56.693;

    /** @return array<string, string> */
    public function profile(User $user): array
    {
        return collect(['name', 'jabatan', 'unit_kerja', 'pangkat', 'golongan'])
            ->mapWithKeys(fn (string $key): array => [$key => trim((string) $user->getAttribute($key))])->all();
    }

    /** @param array<string, string> $profile */
    public function fingerprint(array $profile): string
    {
        return hash('sha256', json_encode($profile, JSON_THROW_ON_ERROR));
    }

    /** @return array<int, array<string, mixed>> */
    public function options(DocumentCycle $cycle, bool $previews = true): array
    {
        $steps = $cycle->signatures()->with('user')->get()->map(function ($step): array {
            $profile = $this->profile($step->user);

            return [...$step->only(['id', 'sequence', 'name_snapshot', 'page', 'x', 'y', 'width', 'height', 'specimen_format', 'specimen_scope', 'specimen_pages']), 'profile_snapshot' => $profile, 'profile_fingerprint' => $this->fingerprint($profile)];
        });
        $result = app(PdfEngine::class)->run('specimens', $cycle->original_path, [
            'steps' => $steps->all(), 'previews' => $previews,
            'verification_url' => rtrim((string) config('signwork.verification_base_url'), '/').route('verification.show', $cycle->public_id, false),
        ]);

        return $steps->map(function (array $step, int $index) use ($result): array {
            $layouts = $result['layouts'][$index];

            // Keep aliases for older saved fixtures while exposing the new UI keys.
            $layouts['qr_2x2'] = $layouts['qr_2cm'];
            $layouts['qr_3x3'] = $layouts['qr_3cm'];

            return [
                ...collect($step)->only(['id', 'sequence', 'name_snapshot', 'page', 'x', 'y', 'width', 'height', 'specimen_format', 'specimen_scope', 'specimen_pages', 'profile_fingerprint'])->all(),
                'layouts' => $layouts,
            ];
        })->all();
    }
}
