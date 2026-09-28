<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Enums\WorkflowMasterType;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;

class SignWorkDemoSeeder extends Seeder
{
    /** @var list<array{name: string, email: string, jabatan: string, unit_kerja: string, pangkat: string, golongan: string}> */
    private const USERS = [
        ['name' => 'Akbar Wira Nugraha, S.Kom', 'email' => 'akbar@signwork.test', 'jabatan' => 'Pranata Komputer Ahli Pertama', 'unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Subang', 'pangkat' => 'Penata Muda Tk. I', 'golongan' => 'III/b'],
        ['name' => 'Siti Rahmawati, S.E.', 'email' => 'siti@signwork.test', 'jabatan' => 'Analis Keuangan Pusat dan Daerah Ahli Muda', 'unit_kerja' => 'Badan Pendapatan Daerah Kabupaten Subang', 'pangkat' => 'Penata', 'golongan' => 'III/c'],
        ['name' => 'Dedi Kurniawan, S.Kom', 'email' => 'dedi@signwork.test', 'jabatan' => 'Pranata Komputer Ahli Muda', 'unit_kerja' => 'Dinas Kesehatan Kabupaten Subang', 'pangkat' => 'Penata Tk. I', 'golongan' => 'III/d'],
        ['name' => 'Rina Marlina, S.KM.', 'email' => 'rina@signwork.test', 'jabatan' => 'Administrator Kesehatan Ahli Muda', 'unit_kerja' => 'Dinas Kesehatan Kabupaten Subang', 'pangkat' => 'Penata Tk. I', 'golongan' => 'III/d'],
        ['name' => 'Fajar Nugraha, S.T.', 'email' => 'fajar@signwork.test', 'jabatan' => 'Penata Perizinan Ahli Pertama', 'unit_kerja' => 'DPMPTSP Kabupaten Subang', 'pangkat' => 'Penata Muda', 'golongan' => 'III/a'],
        ['name' => 'Nabila Putri, S.Sos.', 'email' => 'nabila@signwork.test', 'jabatan' => 'Perencana Ahli Muda', 'unit_kerja' => 'Bappeda Kabupaten Subang', 'pangkat' => 'Penata', 'golongan' => 'III/c'],
    ];

    /** @var list<array{code: string, title: string}> */
    private const DOCUMENT_TYPES = [
        ['code' => 'ND', 'title' => 'Nota Dinas Koordinasi Kegiatan'],
        ['code' => 'ST', 'title' => 'Surat Tugas Tim Teknis'],
        ['code' => 'BA', 'title' => 'Berita Acara Verifikasi'],
        ['code' => 'LH', 'title' => 'Laporan Hasil Analisis'],
        ['code' => 'SP', 'title' => 'Surat Permohonan Dukungan'],
    ];

    /** @var list<DocumentStatus> */
    private const DOCUMENT_STATUSES = [
        DocumentStatus::Draft,
        DocumentStatus::WaitingApproval,
        DocumentStatus::Rejected,
        DocumentStatus::WaitingSignature,
        DocumentStatus::Signed,
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('SignWorkDemoSeeder tidak boleh dijalankan pada production.');
        }

        DB::transaction(function (): void {
            $superadmin = $this->upsertAccount('Superadmin SignWork', 'superadmin@signwork.test', UserRole::Superadmin, [
                'unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Subang',
            ]);
            $admin = $this->upsertAccount('Admin SignWork', 'admin@signwork.test', UserRole::Admin, [
                'unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Subang',
            ]);
            $users = collect(self::USERS)->map(fn (array $data): User => $this->upsertDemoUser($data))->values();

            $this->seedWorkflowMaster($users);
            $this->seedDocuments($users);
            $this->seedActivityLogs($superadmin, $admin, $users->firstOrFail());
        });

        $this->command?->info('SignWork demo selesai: 2 akun admin, 6 user, dokumen multi-status, workflow master, dan log aktivitas dibuat.');
        $this->command?->info('Password seluruh akun demo: password');
    }

    /** @param array<string, string> $profile */
    private function upsertAccount(string $name, string $email, UserRole $role, array $profile = []): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('password'),
            'role' => $role,
            'email_verified_at' => now(),
            ...$profile,
        ])->save();

        return $user->refresh();
    }

    /** @param array{name: string, email: string, jabatan: string, unit_kerja: string, pangkat: string, golongan: string} $data */
    private function upsertDemoUser(array $data): User
    {
        return $this->upsertAccount($data['name'], $data['email'], UserRole::User, [
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit_kerja'],
            'pangkat' => $data['pangkat'],
            'golongan' => $data['golongan'],
        ]);
    }

    /** @param Collection<int, User> $users */
    private function seedWorkflowMaster(Collection $users): void
    {
        foreach ($users as $index => $owner) {
            foreach (WorkflowMasterType::cases() as $type) {
                $targets = collect([$owner, $users[($index + 1) % $users->count()]])->unique('id')->values();

                foreach ($targets as $targetIndex => $target) {
                    WorkflowMasterEntry::query()->updateOrCreate(
                        ['user_id' => $owner->id, 'type' => $type->value, 'target_user_id' => $target->id],
                        ['is_default' => $targetIndex === 0],
                    );
                }
            }
        }
    }

    /** @param Collection<int, User> $users */
    private function seedDocuments(Collection $users): void
    {
        $fixture = base_path('database/seeders/fixtures/demo-workflow.pdf');
        $contents = is_file($fixture) ? file_get_contents($fixture) : false;
        $contents = is_string($contents) ? $contents : null;
        $sequence = 1;

        foreach ($users as $ownerIndex => $owner) {
            foreach (self::DOCUMENT_STATUSES as $statusIndex => $status) {
                $type = self::DOCUMENT_TYPES[($sequence - 1) % count(self::DOCUMENT_TYPES)];
                $approvers = [$owner, $users[($ownerIndex + 1) % $users->count()]];
                $signers = [$owner, $users[($ownerIndex + 2) % $users->count()]];
                $number = sprintf('%03d/SW-DEMO/%s/IX/2026', $sequence, $type['code']);
                $document = Document::query()->firstOrCreate(
                    ['document_number' => $number],
                    [
                        'owner_id' => $owner->id,
                        'destination_user_id' => $users[($ownerIndex + 3) % $users->count()]->id,
                        'approver_id' => $approvers[0]->id,
                        'signer_id' => $signers[0]->id,
                        'title' => $type['title'].' #'.str_pad((string) ($statusIndex + 1), 2, '0', STR_PAD_LEFT),
                        'description' => 'Dokumen dummy SignWork untuk pengujian alur pembuatan, verifikasi, tanda tangan, QR, dan dokumen masuk.',
                        'status' => $status,
                        'revision_count' => 0,
                        'submitted_at' => $status === DocumentStatus::Draft ? null : now()->subDays(2),
                        'approved_at' => in_array($status, [DocumentStatus::WaitingSignature, DocumentStatus::Signed], true) ? now()->subDay() : null,
                        'rejected_at' => $status === DocumentStatus::Rejected ? now()->subDay() : null,
                        'rejection_reason' => $status === DocumentStatus::Rejected ? 'Contoh dokumen ditolak untuk simulasi revisi.' : null,
                        'signer_assigned_at' => in_array($status, [DocumentStatus::WaitingSignature, DocumentStatus::Signed], true) ? now()->subDay() : null,
                        'signed_at' => $status === DocumentStatus::Signed ? now()->subHours(8) : null,
                        'sent_at' => $status === DocumentStatus::Signed ? now()->subHours(6) : null,
                        'sent_by' => $status === DocumentStatus::Signed ? $signers[1]->id : null,
                        'requires_pdf_workflow' => true,
                    ],
                );

                if ($status !== DocumentStatus::Draft && $document->workflow_cycle === 0) {
                    $this->seedCycle($document, $status, $approvers, $signers, $contents);
                }

                $sequence++;
            }
        }
    }

    /** @param list<User> $approvers @param list<User> $signers */
    private function seedCycle(Document $document, DocumentStatus $status, array $approvers, array $signers, ?string $contents): void
    {
        $path = 'signwork/demo/'.$document->uuid.'/source.pdf';
        if ($contents !== null) {
            Storage::disk('local')->put($path, $contents);
        }
        $hash = $contents === null ? null : hash('sha256', $contents);
        $cycle = $document->cycles()->create([
            'number' => 1,
            'title' => $document->title,
            'document_number' => $document->document_number,
            'status' => match ($status) {
                DocumentStatus::Rejected => 'rejected',
                DocumentStatus::WaitingSignature => 'waiting_signature',
                DocumentStatus::Signed => 'signed',
                default => 'waiting_approval',
            },
            'original_path' => $path,
            'original_sha256' => $hash,
            'current_path' => $path,
            'current_sha256' => $hash,
            'final_sha256' => $status === DocumentStatus::Signed ? $hash : null,
            'submitted_at' => now()->subDays(2),
            'completed_at' => $status === DocumentStatus::Signed ? now()->subHours(8) : null,
            'positions_confirmed_at' => now()->subDays(2),
        ]);

        foreach ($approvers as $index => $approver) {
            $approvalStatus = match (true) {
                $status === DocumentStatus::Rejected && $index === 0 => 'rejected',
                $status === DocumentStatus::Rejected => 'not_processed',
                $status === DocumentStatus::WaitingApproval => 'pending',
                default => 'approved',
            };
            $cycle->approvals()->create([
                'user_id' => $approver->id,
                'sequence' => $index + 1,
                'status' => $approvalStatus,
                'name_snapshot' => $approver->name,
                'rejection_reason' => $approvalStatus === 'rejected' ? $document->rejection_reason : null,
                'acted_at' => $approvalStatus === 'pending' ? null : now()->subDay(),
            ]);
        }

        foreach ($signers as $index => $signer) {
            $signed = $status === DocumentStatus::Signed;
            $cycle->signatures()->create([
                'user_id' => $signer->id,
                'sequence' => $index + 1,
                'status' => $signed ? 'signed' : 'pending',
                'name_snapshot' => $signer->name,
                'acted_at' => $signed ? now()->subHours(8) : null,
                'profile_snapshot' => $signer->only(['name', 'jabatan', 'unit_kerja', 'pangkat', 'golongan']),
                'placeholder' => '${tte:signer:'.($index + 1).'}',
                'page' => 1,
                'x' => 36,
                'y' => 36 + ($index * 120),
                'width' => 56.693,
                'height' => 56.693,
                'placement_source' => 'demo',
                'specimen_format' => 'qr_2cm',
                'specimen_scope' => 'all_pages',
                'input_sha256' => $signed ? $hash : null,
                'output_sha256' => $signed ? $hash : null,
                'output_path' => $signed ? $path : null,
                'provider_transaction_id' => $signed ? 'DEMO-'.$document->id.'-'.($index + 1) : null,
            ]);
        }

        $document->update(['workflow_cycle' => $cycle->number]);
    }

    private function seedActivityLogs(User $superadmin, User $admin, User $user): void
    {
        ActivityLog::query()->firstOrCreate(
            ['event' => 'seed.demo', 'message' => 'Dataset demo SignWork dibuat.'],
            ['user_id' => $superadmin->id, 'outcome' => 'success', 'http_status' => 200],
        );
        ActivityLog::query()->firstOrCreate(
            ['event' => 'documents.submit', 'message' => 'Dokumen dummy berhasil diajukan.'],
            ['user_id' => $user->id, 'outcome' => 'success', 'http_status' => 302],
        );
        ActivityLog::query()->firstOrCreate(
            ['event' => 'users.created', 'message' => 'Akun admin demo tersedia.'],
            ['user_id' => $admin->id, 'outcome' => 'success', 'http_status' => 302],
        );
    }
}
