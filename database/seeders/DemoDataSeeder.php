<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use LogicException;

class DemoDataSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, email: string}>
     */
    private const DEMO_USERS = [
        ['name' => 'Andi Pratama', 'email' => 'andi.pratama@demo.signwork.test'],
        ['name' => 'Siti Rahmawati', 'email' => 'siti.rahmawati@demo.signwork.test'],
        ['name' => 'Dedi Kurniawan', 'email' => 'dedi.kurniawan@demo.signwork.test'],
        ['name' => 'Rina Marlina', 'email' => 'rina.marlina@demo.signwork.test'],
        ['name' => 'Fajar Nugraha', 'email' => 'fajar.nugraha@demo.signwork.test'],
        ['name' => 'Nabila Putri', 'email' => 'nabila.putri@demo.signwork.test'],
        ['name' => 'Rizky Hidayat', 'email' => 'rizky.hidayat@demo.signwork.test'],
        ['name' => 'Budi Santoso', 'email' => 'budi.santoso@demo.signwork.test'],
        ['name' => 'Maya Lestari', 'email' => 'maya.lestari@demo.signwork.test'],
        ['name' => 'Arief Maulana', 'email' => 'arief.maulana@demo.signwork.test'],
        ['name' => 'Dewi Kartika', 'email' => 'dewi.kartika@demo.signwork.test'],
        ['name' => 'Rudi Hartono', 'email' => 'rudi.hartono@demo.signwork.test'],
        ['name' => 'Intan Permata', 'email' => 'intan.permata@demo.signwork.test'],
        ['name' => 'Yoga Saputra', 'email' => 'yoga.saputra@demo.signwork.test'],
        ['name' => 'Lina Oktaviani', 'email' => 'lina.oktaviani@demo.signwork.test'],
    ];

    /**
     * @var array<int, DocumentStatus>
     */
    private const STATUSES = [
        DocumentStatus::Draft,
        DocumentStatus::WaitingApproval,
        DocumentStatus::Rejected,
        DocumentStatus::WaitingSignature,
    ];

    /**
     * @var array<int, array{code: string, title: string}>
     */
    private const TYPES = [
        ['code' => 'ND', 'title' => 'Nota Dinas Koordinasi Kegiatan'],
        ['code' => 'SP', 'title' => 'Surat Permohonan Dukungan'],
        ['code' => 'LK', 'title' => 'Laporan Pelaksanaan Kegiatan'],
        ['code' => 'BA', 'title' => 'Berita Acara Verifikasi'],
        ['code' => 'ST', 'title' => 'Surat Tugas Tim Teknis'],
        ['code' => 'RK', 'title' => 'Rencana Kerja Pelaksanaan'],
        ['code' => 'EV', 'title' => 'Dokumen Evaluasi Pelaksanaan'],
        ['code' => 'LA', 'title' => 'Laporan Hasil Analisis'],
        ['code' => 'PA', 'title' => 'Permohonan Akses Sistem'],
        ['code' => 'LH', 'title' => 'Laporan Hasil Verifikasi'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException(
                'DemoDataSeeder tidak boleh dijalankan pada production.'
            );
        }

        $demoUsers = collect(self::DEMO_USERS)
            ->map(
                fn (array $data, int $index): User => $this->upsertUser(
                    $data,
                    $index,
                )
            )
            ->values();

        $workflowUsers = User::query()
            ->where(
                'role',
                UserRole::User->value
            )
            ->orderBy('id')
            ->get();

        $this->seedWorkflowMasterEntries(
            $workflowUsers
        );

        $sequence = 1;

        foreach (
            $demoUsers as $userIndex => $owner
        ) {
            $documentCount =
                5 + ($userIndex % 6);

            for (
                $documentIndex = 0;
                $documentIndex < $documentCount;
                $documentIndex++
            ) {
                $status = self::STATUSES[
                    ($sequence - 1)
                    % count(self::STATUSES)
                ];

                $type = self::TYPES[
                    ($sequence - 1)
                    % count(self::TYPES)
                ];

                $this->upsertDocument(
                    owner: $owner,
                    documentIndex: $documentIndex,
                    sequence: $sequence,
                    status: $status,
                    type: $type,
                );

                $sequence++;
            }
        }

        $this->command?->info(
            sprintf(
                'Demo data selesai: %d user demo dan %d dokumen.',
                $demoUsers->count(),
                $sequence - 1
            )
        );
    }

    /**
     * @param  array{name: string, email: string}  $data
     */
    private function upsertUser(
        array $data,
        int $index,
    ): User {
        $user = User::query()
            ->where(
                'email',
                $data['email']
            )
            ->first();

        if (! $user) {
            return User::factory()
                ->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'nik' => sprintf('999900000000%04d', $index + 1),
                    'password' => 'password',
                    'role' => UserRole::User,
                ]);
        }

        $user->forceFill([
            'name' => $data['name'],
            'nik' => sprintf('999900000000%04d', $index + 1),
            'password' => 'password',
            'role' => UserRole::User,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }

    /**
     * Setiap user memiliki minimal dua pilihan per kategori.
     * Pilihan pertama menjadi default.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedWorkflowMasterEntries(
        Collection $users
    ): void {
        if ($users->isEmpty()) {
            return;
        }

        foreach (
            $users->values() as $index => $owner
        ) {
            $count = $users->count();

            $targets = [
                WorkflowMasterType::Destination->value => [
                    $users[
                        ($index + 1) % $count
                    ],
                    $users[
                        ($index + 4) % $count
                    ],
                ],
                WorkflowMasterType::Approver->value => [
                    $index % 4 === 0
                        ? $owner
                        : $users[
                            ($index + 2) % $count
                        ],
                    $users[
                        ($index + 5) % $count
                    ],
                ],
                WorkflowMasterType::Signer->value => [
                    $index % 5 === 0
                        ? $owner
                        : $users[
                            ($index + 3) % $count
                        ],
                    $users[
                        ($index + 6) % $count
                    ],
                ],
            ];

            foreach (
                $targets as $type => $typeTargets
            ) {
                WorkflowMasterEntry::query()
                    ->where(
                        'user_id',
                        $owner->id
                    )
                    ->where(
                        'type',
                        $type
                    )
                    ->update([
                        'is_default' => false,
                    ]);

                foreach (
                    collect($typeTargets)
                        ->unique('id')
                        ->values() as $targetIndex => $target
                ) {
                    WorkflowMasterEntry::query()
                        ->updateOrCreate(
                            [
                                'user_id' => $owner->id,
                                'type' => $type,
                                'target_user_id' => $target->id,
                            ],
                            [
                                'is_default' => $targetIndex === 0,
                            ]
                        );
                }
            }
        }
    }

    /**
     * @param  array{code: string, title: string}  $type
     */
    private function upsertDocument(
        User $owner,
        int $documentIndex,
        int $sequence,
        DocumentStatus $status,
        array $type
    ): void {
        $createdAt = now()
            ->subDays(
                120 - ($sequence % 90)
            )
            ->subMinutes(
                $documentIndex * 7
            );

        $submittedAt = null;
        $approverAssignedAt = null;
        $approvedAt = null;
        $rejectedAt = null;
        $rejectionReason = null;
        $signerAssignedAt = null;

        if (
            $status
            !== DocumentStatus::Draft
        ) {
            $submittedAt =
                $createdAt
                    ->copy()
                    ->addDay();

            $approverAssignedAt =
                $submittedAt
                    ->copy()
                    ->addHours(2);
        }

        if (
            $status
            === DocumentStatus::Rejected
        ) {
            $rejectedAt =
                $approverAssignedAt
                    ?->copy()
                    ->addDay();

            $rejectionReason = match (
                $sequence % 4
            ) {
                0 => 'Mohon perbaiki nomor dan referensi dokumen.',
                1 => 'Substansi dokumen perlu dilengkapi.',
                2 => 'Uraian dokumen perlu dikoreksi.',
                default => 'Dokumen perlu disesuaikan dengan hasil review.',
            };
        }

        if (
            $status
            === DocumentStatus::WaitingSignature
        ) {
            $approvedAt =
                $approverAssignedAt
                    ?->copy()
                    ->addDay();

            $signerAssignedAt =
                $approvedAt
                    ?->copy()
                    ->addHours(3);
        }

        $documentNumber = sprintf(
            '%03d/SF-DEMO/%s/IX/2026',
            $sequence,
            $type['code']
        );

        $document = Document::query()
            ->firstOrNew([
                'document_number' => $documentNumber,
            ]);

        if ($document->workflow_cycle > 0) {
            return;
        }

        $document->forceFill([
            'requires_pdf_workflow' => false,
            'owner_id' => $owner->id,
            'destination_user_id' => $this->defaultTarget(
                $owner,
                WorkflowMasterType::Destination
            ),
            'approver_id' => $this->defaultTarget(
                $owner,
                WorkflowMasterType::Approver
            ),
            'signer_id' => $this->defaultTarget(
                $owner,
                WorkflowMasterType::Signer
            ),
            'document_number' => $documentNumber,
            'title' => sprintf(
                '%s #%02d',
                $type['title'],
                $documentIndex + 1
            ),
            'description' => sprintf(
                'Dokumen dummy SignWork milik %s. Assignment mengikuti default Data Master pemilik dokumen.',
                $owner->name
            ),
            'status' => $status,
            'submitted_at' => $submittedAt,
            'approver_assigned_at' => $approverAssignedAt,
            'approved_at' => $approvedAt,
            'signer_assigned_at' => $signerAssignedAt,
            'rejected_at' => $rejectedAt,
            'rejection_reason' => $rejectionReason,
            'revision_count' => 0,
            'last_revised_at' => null,
            'signed_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $signerAssignedAt
                ?? $rejectedAt
                ?? $approverAssignedAt
                ?? $createdAt,
        ]);

        $document->save();
    }

    private function defaultTarget(
        User $owner,
        WorkflowMasterType $type
    ): int {
        return (int) $owner
            ->workflowMasterEntries()
            ->where(
                'type',
                $type->value
            )
            ->orderByDesc(
                'is_default'
            )
            ->orderBy('id')
            ->value(
                'target_user_id'
            );
    }
}
