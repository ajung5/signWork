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
use Illuminate\Support\Facades\Hash;
use LogicException;

class FiveUserDocumentSeeder extends Seeder
{
    /** @var array<int, array{name: string, email: string}> */
    private const USERS = [
        ['name' => 'Ahmad Fauzan, S.Kom', 'email' => 'sample01@demo.signwork.test'],
        ['name' => 'Nisa Ramadhani, S.E.', 'email' => 'sample02@demo.signwork.test'],
        ['name' => 'Rendi Saputra, S.K.M.', 'email' => 'sample03@demo.signwork.test'],
        ['name' => 'Putri Maharani, S.Kom', 'email' => 'sample04@demo.signwork.test'],
        ['name' => 'Galih Prasetyo, S.T.', 'email' => 'sample06@demo.signwork.test'],
    ];

    /** @var array<int, DocumentStatus> */
    private const STATUSES = [
        DocumentStatus::Draft,
        DocumentStatus::WaitingApproval,
        DocumentStatus::Rejected,
        DocumentStatus::WaitingSignature,
    ];

    /** @var array<int, array{code: string, title: string}> */
    private const TYPES = [
        ['code' => 'ND', 'title' => 'Nota Dinas Koordinasi'],
        ['code' => 'ST', 'title' => 'Surat Tugas'],
        ['code' => 'BA', 'title' => 'Berita Acara'],
        ['code' => 'LH', 'title' => 'Laporan Hasil'],
        ['code' => 'SP', 'title' => 'Surat Permohonan'],
        ['code' => 'EV', 'title' => 'Dokumen Evaluasi'],
        ['code' => 'RK', 'title' => 'Rencana Kerja'],
        ['code' => 'PA', 'title' => 'Permohonan Akses'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('FiveUserDocumentSeeder hanya boleh dijalankan pada APP_ENV=local atau testing.');
        }

        $password = Hash::make('SignWorkDemo!2026');

        $users = collect(self::USERS)
            ->map(fn (array $data): User => $this->upsertUser($data, $password))
            ->values();

        $this->call(UserProfileSeeder::class);
        $users = User::query()->whereIn('email', collect(self::USERS)->pluck('email'))->orderBy('id')->get();

        $this->seedWorkflowMasterEntries($users);

        $sequence = 1;

        foreach ($users as $owner) {
            for ($documentIndex = 0; $documentIndex < 15; $documentIndex++) {
                $status = self::STATUSES[$documentIndex % count(self::STATUSES)];
                $type = self::TYPES[$documentIndex % count(self::TYPES)];

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

        $this->command?->info('FiveUserDocumentSeeder selesai: 5 user demo dan 75 dokumen (15 dokumen per user).');
        $this->command?->info('Password seluruh user demo tambahan: SignWorkDemo!2026');
    }

    /** @param array{name: string, email: string} $data */
    private function upsertUser(array $data, string $password): User
    {
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'role' => UserRole::User,
                'email_verified_at' => now(),
            ])->save();

            return $user->refresh();
        }

        $user->forceFill([
            'name' => $data['name'],
            'role' => UserRole::User,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user->refresh();
    }

    /** @param Collection<int, User> $users */
    private function seedWorkflowMasterEntries(Collection $users): void
    {
        $count = $users->count();

        foreach ($users->values() as $index => $owner) {
            $targets = [
                WorkflowMasterType::Destination->value => [1, 2],
                WorkflowMasterType::Approver->value => [2, 3],
                WorkflowMasterType::Signer->value => [3, 4],
            ];

            foreach ($targets as $type => $offsets) {
                WorkflowMasterEntry::query()
                    ->where('user_id', $owner->id)
                    ->where('type', $type)
                    ->update(['is_default' => false]);

                foreach ($offsets as $order => $offset) {
                    $target = $users[($index + $offset) % $count];

                    WorkflowMasterEntry::query()->updateOrCreate(
                        [
                            'user_id' => $owner->id,
                            'type' => $type,
                            'target_user_id' => $target->id,
                        ],
                        [
                            'is_default' => $order === 0,
                        ]
                    );
                }
            }
        }
    }

    /** @param array{code: string, title: string} $type */
    private function upsertDocument(
        User $owner,
        int $documentIndex,
        int $sequence,
        DocumentStatus $status,
        array $type
    ): void {
        $documentNumber = sprintf('%03d/SF-SEED/%s/IX/2026', $sequence, $type['code']);

        if (Document::query()->where('document_number', $documentNumber)->exists()) {
            return;
        }

        $createdAt = now()->subDays(90 - ($sequence % 60))->subMinutes($documentIndex * 5);
        $submittedAt = $status === DocumentStatus::Draft ? null : $createdAt->copy()->addDay();
        $approverAssignedAt = $status === DocumentStatus::Draft ? null : $submittedAt?->copy()->addHours(2);
        $approvedAt = $status === DocumentStatus::WaitingSignature ? $approverAssignedAt?->copy()->addDay() : null;
        $rejectedAt = $status === DocumentStatus::Rejected ? $approverAssignedAt?->copy()->addDay() : null;
        $signerAssignedAt = $status === DocumentStatus::WaitingSignature ? $approvedAt?->copy()->addHours(2) : null;

        $document = new Document;
        $document->forceFill([
            'requires_pdf_workflow' => false,
            'owner_id' => $owner->id,
            'destination_user_id' => $this->defaultTarget($owner, WorkflowMasterType::Destination),
            'approver_id' => $this->defaultTarget($owner, WorkflowMasterType::Approver),
            'signer_id' => $this->defaultTarget($owner, WorkflowMasterType::Signer),
            'document_number' => $documentNumber,
            'title' => sprintf('%s #%02d', $type['title'], $documentIndex + 1),
            'description' => sprintf('Dokumen demo tambahan milik %s untuk pengujian workflow SignWork.', $owner->name),
            'status' => $status,
            'submitted_at' => $submittedAt,
            'approver_assigned_at' => $approverAssignedAt,
            'approved_at' => $approvedAt,
            'signer_assigned_at' => $signerAssignedAt,
            'rejected_at' => $rejectedAt,
            'rejection_reason' => $status === DocumentStatus::Rejected ? 'Dokumen demo ditolak untuk kebutuhan pengujian alur revisi.' : null,
            'revision_count' => 0,
            'last_revised_at' => null,
            'signed_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $signerAssignedAt ?? $rejectedAt ?? $approverAssignedAt ?? $createdAt,
        ])->save();
    }

    private function defaultTarget(User $owner, WorkflowMasterType $type): int
    {
        return (int) $owner->workflowMasterEntries()
            ->where('type', $type->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('target_user_id');
    }
}
