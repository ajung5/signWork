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
        $signatureModels = $cycle->signatures()->with('user')->get();
        $signerCount = $signatureModels->count();
        $placeholders = is_array($cycle->pdf_metadata['placeholders'] ?? null)
            ? $cycle->pdf_metadata['placeholders']
            : [];
        $steps = $signatureModels->map(function ($step) use ($placeholders, $signerCount): array {
            $profile = is_array($step->profile_snapshot) && $step->profile_snapshot !== []
                ? $step->profile_snapshot
                : $this->profile($step->user);
            $matches = $placeholders[$step->placeholder] ?? [];
            if (! $matches && $step->placeholder === '${tte:signer:1}' && $signerCount === 1) {
                $matches = $placeholders['${tandatangan_naskah}'] ?? [];
            }
            $placeholderRects = collect(is_array($matches) ? $matches : [])
                ->mapWithKeys(function (array $match): array {
                    $page = (int) ($match['page'] ?? 0);
                    $rect = is_array($match['text_rect'] ?? null) ? array_values($match['text_rect']) : [];
                    $x = (float) ($rect[0] ?? $match['x'] ?? 0);
                    $y = (float) ($rect[1] ?? $match['y'] ?? 0);
                    $right = (float) ($rect[2] ?? ($x + (float) ($match['width'] ?? self::QR_SIZE)));
                    $bottom = (float) ($rect[3] ?? ($y + (float) ($match['height'] ?? self::QR_SIZE)));

                    return [(string) $page => [
                        'x' => $x,
                        'y' => $y,
                        'width' => max(0, $right - $x),
                        'height' => max(0, $bottom - $y),
                    ]];
                })
                ->filter(fn (array $rect, string $page): bool => (int) $page > 0 && $rect['width'] > 0 && $rect['height'] > 0)
                ->all();

            return [...$step->only(['id', 'sequence', 'name_snapshot', 'placeholder', 'placement_source', 'page', 'x', 'y', 'width', 'height', 'specimen_format', 'specimen_scope', 'specimen_pages', 'specimen_positions']), 'placeholder_rects' => $placeholderRects, 'profile_snapshot' => $profile, 'profile_fingerprint' => $this->fingerprint($profile)];
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
                ...collect($step)->only(['id', 'sequence', 'name_snapshot', 'placeholder', 'placement_source', 'page', 'x', 'y', 'width', 'height', 'specimen_format', 'specimen_scope', 'specimen_pages', 'specimen_positions', 'placeholder_rects', 'profile_fingerprint'])->all(),
                'layouts' => $layouts,
            ];
        })->all();
    }
}
