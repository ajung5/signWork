<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentCycle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentWorkflow {
    public function __construct(private PdfEngine $pdf, private SigningProvider $provider) {}

    /** @param list<int> $approvers @param list<int> $signers */
    public function configure(
        Document $document,
        User $actor,
        array $approvers,
        array $signers,
        ?UploadedFile $upload
    ): void {
        $newPath = null;
        $sourcePath = null;
        try {
            DB::transaction(function () use (
                $document,
                $actor,
                $approvers,
                $signers,
                $upload,
                &$newPath,
                &$sourcePath
            ): void {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize('update', $locked);
                $previous = $locked->currentCycle();
                foreach (['approver' => $approvers, 'signer' => $signers] as $type => $ids) {
                    $existing = $previous
                        ? ($type === 'approver' ? $previous->approvals() : $previous->signatures())
                            ->pluck('user_id')
                            ->all()
                        : [];
                    $existing[] = $type === 'approver' ? $locked->approver_id : $locked->signer_id;
                    foreach ($ids as $id) {
                        $user = User::query()->findOrFail($id);
                        $allowed =
                            $user->id === $actor->id ||
                            in_array($user->id, $existing, true) ||
                            $actor
                                ->workflowMasterEntries()
                                ->where('type', $type)
                                ->where('target_user_id', $id)
                                ->exists();
                        if ($user->isAdmin() || !$allowed) {
                            throw ValidationException::withMessages([
                                $type =>
                                    'Peserta harus non-admin dan berasal dari Data Master Anda atau assignment dokumen ini.'
                            ]);
                        }
                    }
                }
                if (!$upload && !$previous?->original_path) {
                    throw ValidationException::withMessages(['pdf' => 'Unggah PDF untuk memulai workflow.']);
                }
                $path = $previous?->original_path;
                $metadata = $previous?->pdf_metadata;
                $hash = $previous?->original_sha256;
                if ($upload) {
                    if ($upload->getSize() > 5 * 1024 * 1024) {
                        throw ValidationException::withMessages(['pdf' => 'Ukuran file maksimal 5 MB.']);
                    }
                    $extension = strtolower($upload->getClientOriginalExtension());
                    if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
                        throw ValidationException::withMessages(['pdf' => 'Gunakan PDF, DOC, atau DOCX.']);
                    }
                    $sourcePath = $upload->storeAs(
                        'signwork/' . $locked->uuid,
                        Str::uuid() . '.' . $extension,
                        'local'
                    );
                    $newPath = $sourcePath;
                    if ($sourcePath && $extension !== 'pdf') {
                        $newPath = app(WordConverter::class)->convert($sourcePath);
                    }
                    if (!$newPath) {
                        throw ValidationException::withMessages(['pdf' => 'PDF gagal disimpan.']);
                    }
                    $path = $newPath;
                    $metadata = $this->pdf->run('scan', $path);
                    $hash = $this->pdf->hash($path);
                } else {
                    $this->pdf->ensureHash($path, $hash);
                }
                $expectedTokens = [];
                foreach (array_keys($signers) as $index) {
                    $expectedTokens[] = '${tte:signer:' . ($index + 1) . '}';
                }
                if (count($signers) === 1) {
                    $expectedTokens[] = '${tandatangan_naskah}';
                }
                if (array_diff(array_keys($metadata['placeholders']), $expectedTokens)) {
                    throw ValidationException::withMessages([
                        'pdf' =>
                            'Ada placeholder tanpa signer yang sesuai. Sesuaikan template dengan jumlah dan urutan signer.'
                    ]);
                }
                if (
                    isset(
                        $metadata['placeholders']['${tte:signer:1}'],
                        $metadata['placeholders']['${tandatangan_naskah}']
                    )
                ) {
                    throw ValidationException::withMessages([
                        'pdf' => 'Gunakan satu format placeholder untuk signer pertama.'
                    ]);
                }
                $cycle = $this->draftCycle($locked);
                $cycle->update([
                    'original_path' => $path,
                    'original_sha256' => $hash,
                    'pdf_metadata' => $metadata,
                    'title' => $locked->title,
                    'document_number' => $locked->document_number,
                    'positions_confirmed_at' => null,
                    'source_path' => $sourcePath ?: $previous?->source_path,
                    'source_name' => $upload
                        ? mb_substr(basename($upload->getClientOriginalName()), 0, 240)
                        : $previous?->source_name,
                    'source_sha256' => $sourcePath ? $this->pdf->hash($sourcePath) : $previous?->source_sha256
                ]);
                $oldSignatures = $cycle->signatures()->get()->keyBy('user_id');
                $cycle->approvals()->delete();
                $cycle->signatures()->delete();
                foreach ($approvers as $index => $id) {
                    $cycle
                        ->approvals()
                        ->create([
                            'user_id' => $id,
                            'sequence' => $index + 1,
                            'name_snapshot' => User::findOrFail($id)->name
                        ]);
                }
                foreach ($signers as $index => $id) {
                    $placeholder = '${tte:signer:' . ($index + 1) . '}';
                    $matches = $metadata['placeholders'][$placeholder] ?? [];
                    if (count($signers) === 1 && !$matches) {
                        $matches = $metadata['placeholders']['${tandatangan_naskah}'] ?? [];
                    }
                    if (count($matches) > 1) {
                        throw ValidationException::withMessages([
                            'pdf' => 'Placeholder ' . $placeholder . ' muncul lebih dari sekali. Perbaiki PDF sumber.'
                        ]);
                    }
                    $position = $matches[0] ?? null;
                    $old = $oldSignatures->get($id);
                    if (!$position && !$upload && $old) {
                        $position = $old->only([
                            'page',
                            'x',
                            'y',
                            'width',
                            'height',
                            'specimen_scope',
                            'specimen_pages',
                            'specimen_positions'
                        ]);
                    }
                    $cycle->signatures()->create([
                        'user_id' => $id,
                        'sequence' => $index + 1,
                        'name_snapshot' => User::findOrFail($id)->name,
                        'placeholder' => $placeholder,
                        'page' => $position['page'] ?? null,
                        'x' => $position['x'] ?? null,
                        'y' => $position['y'] ?? null,
                        'specimen_format' => 'qr_2cm',
                        'specimen_scope' => is_array($position)
                            ? $position['specimen_scope'] ?? 'all_pages'
                            : 'all_pages',
                        'specimen_pages' => is_array($position) ? $position['specimen_pages'] ?? null : null,
                        'specimen_positions' => is_array($position) ? $position['specimen_positions'] ?? null : null,
                        'width' => SpecimenTemplate::QR_SIZE,
                        'height' => SpecimenTemplate::QR_SIZE,
                        'placement_source' => $matches ? 'placeholder' : 'manual'
                    ]);
                }
                $locked->update([
                    'approver_id' => $approvers[0],
                    'signer_id' => $signers[0],
                    'requires_pdf_workflow' => true
                ]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete(array_filter([$newPath, $sourcePath]));
            throw $error;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function updateMetadata(Document $document, User $actor, array $attributes): void {
        DB::transaction(function () use ($document, $actor, $attributes): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $cycle = $this->draftCycle($locked);
            unset($attributes['approver_id'], $attributes['signer_id']);
            $locked->update($attributes);
            $cycle->update(['title' => $locked->title, 'document_number' => $locked->document_number]);
        });
    }

    private function draftCycle(Document $document): DocumentCycle {
        $previous = $document->currentCycle();
        if ($previous && $document->isDraft()) {
            return $previous;
        }
        if ($document->isRejected()) {
            $document->revise();
        }
        $cycle = $document->cycles()->create([
            'specimen_version' => 1,
            'number' => $document->workflow_cycle + 1,
            'title' => $document->title,
            'document_number' => $document->document_number,
            'original_path' => $previous?->original_path,
            'original_sha256' => $previous?->original_sha256,
            'pdf_metadata' => $previous?->pdf_metadata,
            'source_path' => $previous?->source_path,
            'source_name' => $previous?->source_name,
            'source_sha256' => $previous?->source_sha256
        ]);
        if ($previous) {
            foreach ($previous->approvals()->get() as $step) {
                $cycle->approvals()->create($step->only(['user_id', 'name_snapshot', 'sequence']));
            }
            foreach ($previous->signatures()->get() as $step) {
                $cycle
                    ->signatures()
                    ->create(
                        $step->only([
                            'user_id',
                            'name_snapshot',
                            'sequence',
                            'placeholder',
                            'page',
                            'x',
                            'y',
                            'width',
                            'height',
                            'placement_source',
                            'specimen_format',
                            'specimen_scope',
                            'specimen_pages',
                            'profile_snapshot'
                        ])
                    );
            }
        }
        $document->workflow_cycle = $cycle->number;
        $document->approver_id = $cycle->approvals()->first()?->user_id ?? $document->approver_id;
        $document->signer_id = $cycle->signatures()->first()?->user_id ?? $document->signer_id;
        $document->rejected_at = null;
        $document->rejection_reason = null;
        $document->save();

        return $cycle;
    }

    /** @param list<array<string, mixed>> $positions */
    public function place(Document $document, User $actor, string $token, string $sourceHash, array $positions): void {
        DB::transaction(function () use ($document, $actor, $token, $sourceHash, $positions): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('update', $locked);
            abort_unless($locked->isDraft(), 409, 'Simpan revisi terlebih dahulu.');
            $cycle = $this->cycle($locked, $token);
            abort_unless(
                hash_equals($cycle->original_sha256, $sourceHash),
                409,
                'PDF telah diganti. Muat ulang halaman.'
            );
            $steps = $cycle->signatures()->with('user')->get();
            if (count($positions) !== $steps->count()) {
                throw ValidationException::withMessages(['positions' => 'Posisi seluruh signer wajib ditentukan.']);
            }
            $this->pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
            $templates = app(SpecimenTemplate::class);
            $options = $templates->options($cycle, false);
            $validated = [];
            foreach ($steps as $index => $step) {
                abort_unless(
                    (int) $positions[$index]['id'] === $step->id,
                    409,
                    'Urutan signer berubah. Muat ulang halaman.'
                );
                $format = match ($positions[$index]['specimen_format'] ?? 'qr_2cm') {
                    'qr_2x2' => 'qr_2cm',
                    'qr_3x3' => 'qr_3cm',
                    default => $positions[$index]['specimen_format'] ?? 'qr_2cm'
                };
                if (!in_array($format, ['framed', 'qr_2cm', 'qr_3cm'], true)) {
                    throw ValidationException::withMessages(['positions' => 'Pilih format berbingkai atau QR saja.']);
                }
                $scope = $positions[$index]['specimen_scope'] ?? 'selected_page';
                if (!in_array($scope, ['all_pages', 'selected_pages', 'selected_page'], true)) {
                    throw ValidationException::withMessages([
                        'positions' => 'Pilih cakupan semua halaman atau beberapa halaman.'
                    ]);
                }
                $selectedPages = collect($positions[$index]['specimen_pages'] ?? [])
                    ->map(fn(mixed $page): int => (int) $page)
                    ->filter(fn(int $page): bool => $page > 0)
                    ->unique()
                    ->values()
                    ->all();
                if ($scope === 'selected_page') {
                    $selectedPages = [(int) ($positions[$index]['page'] ?? 0)];
                }
                if ($scope === 'selected_pages' && $selectedPages === []) {
                    throw ValidationException::withMessages([
                        'positions' => 'Pilih minimal satu halaman untuk spesimen.'
                    ]);
                }
                $choice = $options[$index]['layouts'][$format];
                if (isset($choice['error'])) {
                    throw ValidationException::withMessages(['positions' => $choice['error']]);
                }
                $profile = $templates->profile($step->user);
                if (
                    $format === 'framed' &&
                    !hash_equals(
                        $templates->fingerprint($profile),
                        (string) ($positions[$index]['profile_fingerprint'] ?? '')
                    )
                ) {
                    throw ValidationException::withMessages([
                        'positions' => 'Profil signer berubah. Muat ulang preview lalu konfirmasi kembali.'
                    ]);
                }
                $basePosition = collect($positions[$index])
                    ->only(['x', 'y'])
                    ->map(fn (mixed $value): float => (float) $value)
                    ->all();
                $rawPagePositions = is_array($positions[$index]['specimen_positions'] ?? null)
                    ? $positions[$index]['specimen_positions']
                    : [];
                $pagePositions = [];
                foreach ($selectedPages as $pageNumber) {
                    $pagePosition = $rawPagePositions[(string) $pageNumber]
                        ?? $rawPagePositions[$pageNumber]
                        ?? null;
                    if ($scope !== 'selected_pages') {
                        $pagePosition ??= $basePosition;
                    }
                    if (! isset($pagePosition['x'], $pagePosition['y'])) {
                        throw ValidationException::withMessages([
                            'positions' => 'Posisi spesimen setiap halaman terpilih wajib ditentukan.'
                        ]);
                    }
                    $pagePositions[(string) $pageNumber] = [
                        'x' => (float) $pagePosition['x'],
                        'y' => (float) $pagePosition['y'],
                        'width' => $choice['width'],
                        'height' => $choice['height'],
                    ];
                }
                $firstPagePosition = $pagePositions[(string) ($selectedPages[0] ?? 0)] ?? [
                    ...$basePosition,
                    'width' => $choice['width'],
                    'height' => $choice['height'],
                ];
                $validated[] = [
                    'page' => $selectedPages[0] ?? (int) ($positions[$index]['page'] ?? 1),
                    'x' => $firstPagePosition['x'],
                    'y' => $firstPagePosition['y'],
                    'width' => $choice['width'],
                    'height' => $choice['height'],
                    'specimen_format' => $format,
                    'specimen_scope' => $scope,
                    'specimen_pages' => $scope === 'all_pages' ? null : $selectedPages,
                    'specimen_positions' => $scope === 'selected_pages' ? $pagePositions : null,
                    'profile_snapshot' => $profile,
                    'name_snapshot' => $profile['name'],
                    'placement_source' => 'manual'
                ];
            }
            $this->pdf->run('validate', $cycle->original_path, ['steps' => $validated]);
            foreach ($steps as $index => $step) {
                $step->update($validated[$index]);
            }
            $cycle->update(['positions_confirmed_at' => now(), 'specimen_version' => 1]);
        });
    }

    public function submit(Document $document, User $actor, string $token): void {
        DB::transaction(function () use ($document, $actor, $token): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('submit', $locked);
            $cycle = $this->cycle($locked, $token);
            if (!$cycle->positions_confirmed_at || !$cycle->approvals()->exists() || !$cycle->signatures()->exists()) {
                throw ValidationException::withMessages([
                    'workflow' => 'Lengkapi alur dan konfirmasi posisi QR terlebih dahulu.'
                ]);
            }
            $this->pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
            $cycle->update([
                'status' => 'waiting_approval',
                'submitted_at' => now(),
                'title' => $locked->title,
                'document_number' => $locked->document_number
            ]);
            $locked->update([
                'status' => DocumentStatus::WaitingApproval,
                'submitted_at' => now(),
                'approver_id' => $cycle->approvals()->firstOrFail()->user_id,
                'approver_assigned_at' => now()
            ]);
        });
    }

    public function decide(Document $document, User $actor, string $token, ?string $reason = null): void {
        $outputs = [];
        try {
            DB::transaction(function () use ($document, $actor, $token, $reason, &$outputs): void {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize($reason === null ? 'approve' : 'reject', $locked);
                $cycle = $this->cycle($locked, $token);
                $step = $cycle->approvals()->where('status', 'pending')->firstOrFail();
                abort_unless($step->user_id === $actor->id, 403);
                $this->pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
                $step->update([
                    'status' => $reason === null ? 'approved' : 'rejected',
                    'acted_at' => now(),
                    'rejection_reason' => $reason
                ]);
                if ($reason !== null) {
                    $cycle
                        ->approvals()
                        ->where('status', 'pending')
                        ->update(['status' => 'not_processed']);
                    $cycle
                        ->signatures()
                        ->where('status', 'pending')
                        ->update(['status' => 'not_processed']);
                    $cycle->update(['status' => 'rejected']);
                    $locked->update([
                        'status' => DocumentStatus::Rejected,
                        'rejection_reason' => $reason,
                        'rejected_at' => now()
                    ]);
                } elseif ($next = $cycle->approvals()->where('status', 'pending')->first()) {
                    $locked->update(['approver_id' => $next->user_id, 'approver_assigned_at' => now()]);
                } else {
                    $outputs[] = $this->prepare($locked, $cycle);
                    $cycle->update(['status' => 'waiting_signature']);
                    $locked->update([
                        'status' => DocumentStatus::WaitingSignature,
                        'approved_at' => now(),
                        'signer_id' => $cycle->signatures()->firstOrFail()->user_id,
                        'signer_assigned_at' => now()
                    ]);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($outputs);
            throw $error;
        }
    }

    public function sign(Document $document, User $actor, string $token): void {
        $outputs = [];
        try {
            DB::transaction(function () use ($document, $actor, $token, &$outputs): void {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize('sign', $locked);
                $cycle = $this->cycle($locked, $token);
                $step = $cycle->signatures()->where('status', 'pending')->firstOrFail();
                abort_unless($step->user_id === $actor->id, 403);
                abort_if($cycle->approvals()->where('status', '!=', 'approved')->exists(), 409);
                if (config('signwork.provider') !== 'mock') {
                    throw ValidationException::withMessages([
                        'provider' => 'Hanya provider mock tersedia. Integrasi BSrE belum aktif.'
                    ]);
                }
                $locked->update(['status' => DocumentStatus::Signing]);
                if (!$cycle->prepared_path) {
                    $outputs[] = $this->prepare($locked, $cycle);
                }

                $this->pdf->ensureHash($cycle->current_path, $cycle->current_sha256);
                $output = 'signwork/' . $locked->uuid . '/' . Str::uuid() . '.pdf';
                $outputs[] = $output;
                $transaction = (string) Str::uuid();
                $context =
                    $cycle->qr_mode === 'per_signer'
                        ? [
                            'step' => $step->toArray(),
                            'verification_url' =>
                                rtrim((string) config('signwork.verification_base_url'), '/') .
                                route('verification.show', $cycle->public_id, false)
                        ]
                        : [];
                $this->provider->sign($cycle->current_path, $output, $transaction, $step->name_snapshot, $context);
                $hash = $this->pdf->hash($output);
                $step->update([
                    'status' => 'signed',
                    'acted_at' => now(),
                    'input_sha256' => $cycle->current_sha256,
                    'output_sha256' => $hash,
                    'output_path' => $output,
                    'provider_transaction_id' => $transaction
                ]);
                $cycle->update(['current_path' => $output, 'current_sha256' => $hash]);
                if ($next = $cycle->signatures()->where('status', 'pending')->first()) {
                    $locked->update([
                        'status' => DocumentStatus::WaitingSignature,
                        'signer_id' => $next->user_id,
                        'signer_assigned_at' => now()
                    ]);
                } else {
                    $cycle->update(['status' => 'signed', 'final_sha256' => $hash, 'completed_at' => now()]);
                    $locked->update(['status' => DocumentStatus::Signed, 'signed_at' => now()]);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($outputs);
            throw $error;
        }
    }

    public function send(Document $document, User $actor, string $token): void {
        DB::transaction(function () use ($document, $actor, $token): void {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('send', $locked);
            $cycle = $this->cycle($locked, $token);
            abort_unless($cycle->status === 'signed' && $cycle->final_sha256 && $locked->destination_user_id, 409);
            $this->pdf->ensureHash($cycle->current_path, $cycle->final_sha256);
            $locked->forceFill(['sent_at' => now(), 'sent_by' => $actor->id])->save();
        });
    }

    private function prepare(Document $document, DocumentCycle $cycle): string {
        $this->pdf->ensureHash($cycle->original_path, $cycle->original_sha256);
        $prepared = 'signwork/' . $document->uuid . '/' . Str::uuid() . '.pdf';
        try {
            $verificationUrl =
                rtrim((string) config('signwork.verification_base_url'), '/') .
                route('verification.show', $cycle->public_id, false);
            $this->pdf->run('prepare', $cycle->original_path, [
                'output' => $this->pdf->path($prepared),
                'steps' => $cycle->signatures()->get()->toArray(),
                'verification_url' => $verificationUrl,
                'specimen_version' => (int) $cycle->specimen_version
            ]);
            $hash = $this->pdf->hash($prepared);
            $cycle->update([
                'prepared_path' => $prepared,
                'prepared_sha256' => $hash,
                'current_path' => $prepared,
                'current_sha256' => $hash
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($prepared);
            throw $error;
        }

        return $prepared;
    }

    private function cycle(Document $document, string $token): DocumentCycle {
        $cycle = $document->currentCycle();
        abort_unless(
            $cycle && hash_equals($cycle->public_id, $token),
            409,
            'Siklus dokumen berubah. Muat ulang halaman.'
        );

        return $cycle;
    }
}
