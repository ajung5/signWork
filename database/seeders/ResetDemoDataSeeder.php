<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentCycle;
use App\Models\DocumentSignatureStep;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use App\Services\DocumentWorkflow;
use App\Services\PdfEngine;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class ResetDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Reset demo hanya boleh pada APP_ENV=local atau testing.');
        }
        if (config('signwork.provider') !== 'mock') {
            throw new LogicException('Reset demo membutuhkan SIGNWORK_PROVIDER=mock.');
        }
        if (! User::whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])->exists()) {
            throw new LogicException('Akun admin harus sudah ada. Tidak ada data yang dihapus.');
        }
        for ($index = 1; $index <= 10; $index++) {
            if (User::whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])->where('email', sprintf('user%02d@demo.signwork.test', $index))->exists()) {
                throw new LogicException('Email demo dipakai akun admin. Tidak ada data yang dihapus.');
            }
        }

        $template = database_path('seeders/fixtures/demo-workflow.pdf');
        if (! is_file($template)) {
            throw new RuntimeException('Template database/seeders/fixtures/demo-workflow.pdf tidak tersedia.');
        }
        $disk = Storage::disk('local');
        $probe = 'signwork/preflight/'.Str::uuid().'.pdf';
        try {
            if (! $disk->put($probe, file_get_contents($template))) {
                throw new RuntimeException('Storage PDF tidak dapat ditulis.');
            }
            app(PdfEngine::class)->run('scan', $probe);
        } finally {
            $disk->delete($probe);
        }

        $oldPaths = DocumentCycle::all(['original_path', 'prepared_path', 'current_path', 'source_path'])
            ->flatMap(fn ($cycle) => [$cycle->original_path, $cycle->prepared_path, $cycle->current_path, $cycle->source_path])
            ->merge(DocumentSignatureStep::pluck('output_path'))->filter()->unique()->values()->all();
        $createdDirectories = [];
        try {
            DB::transaction(function () use ($template, &$createdDirectories): void {
                $removedUsers = User::whereNotIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])->get(['id', 'email']);
                Document::query()->delete();
                WorkflowMasterEntry::query()->delete();
                DB::table('sessions')->whereIn('user_id', $removedUsers->pluck('id'))->delete();
                DB::table('password_reset_tokens')->whereIn('email', $removedUsers->pluck('email'))->delete();
                User::whereNotIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])->delete();

                $users = collect();
                $password = Hash::make('SignWorkDemo!2026');
                $names = ['Andi Pratama', 'Siti Rahmawati', 'Dedi Kurniawan', 'Rina Marlina', 'Fajar Nugraha', 'Nabila Putri', 'Rizky Hidayat', 'Budi Santoso', 'Maya Lestari', 'Arief Maulana'];
                foreach ($names as $index => $name) {
                    $user = new User;
                    $user->forceFill(['name' => $name, 'email' => sprintf('user%02d@demo.signwork.test', $index + 1), 'password' => $password, 'role' => UserRole::User, 'email_verified_at' => now()])->save();
                    $users->push($user);
                }
                foreach ($users as $index => $owner) {
                    foreach (['destination' => [1, 2], 'approver' => [2, 3], 'signer' => [4, 5]] as $type => $offsets) {
                        foreach ($offsets as $order => $offset) {
                            WorkflowMasterEntry::create(['user_id' => $owner->id, 'type' => $type, 'target_user_id' => $users[($index + $offset) % 10]->id, 'is_default' => $order === 0]);
                        }
                    }
                }

                $workflow = app(DocumentWorkflow::class);
                foreach ($users as $index => $owner) {
                    $approvers = [$users[($index + 2) % 10], $users[($index + 3) % 10]];
                    $signers = [$users[($index + 4) % 10], $users[($index + 5) % 10]];
                    for ($number = 1; $number <= 20; $number++) {
                        $document = Document::create([
                            'owner_id' => $owner->id, 'destination_user_id' => $users[($index + 1) % 10]->id,
                            'approver_id' => $approvers[0]->id, 'signer_id' => $signers[0]->id,
                            'document_number' => sprintf('DEMO/%02d/%03d/%s', $index + 1, $number, now()->year),
                            'title' => sprintf('Dokumen Demo %02d - %s', $number, $owner->name),
                            'description' => 'DATA DUMMY: dua approver dan dua signer berurutan. Signing MOCK, bukan TTE BSrE.',
                            'status' => DocumentStatus::Draft,
                        ]);
                        $createdDirectories[] = 'signwork/'.$document->uuid;
                        $workflow->configure($document, $owner, array_column($approvers, 'id'), array_column($signers, 'id'), new UploadedFile($template, 'demo.pdf', 'application/pdf', null, true));
                        $document->refresh();
                        $cycle = $document->currentCycle();
                        if ($number <= 2) {
                            continue;
                        }
                        $workflow->place($document, $owner, $cycle->public_id, $cycle->original_sha256,
                            $cycle->signatures->map->only(['id', 'page', 'x', 'y', 'width', 'height'])->all());
                        if ($number === 3) {
                            continue;
                        }
                        $workflow->submit($document, $owner, $cycle->public_id);
                        if ($number === 4) {
                            $workflow->decide($document, $approvers[0], $cycle->public_id, 'Simulasi revisi: lengkapi lampiran.');
                            $document->refresh();
                            $workflow->updateMetadata($document, $owner, ['title' => $document->title.' (Revisi 1)']);

                            continue;
                        }
                        if ($number <= 6) {
                            continue;
                        }
                        $workflow->decide($document, $approvers[0], $cycle->public_id);
                        if ($number <= 8) {
                            continue;
                        }
                        if ($number <= 12) {
                            $workflow->decide($document, $approvers[1], $cycle->public_id, 'Data dummy ditolak: perbaiki isi dan lampiran sebelum diajukan ulang.');

                            continue;
                        }
                        $workflow->decide($document, $approvers[1], $cycle->public_id);
                        if ($number <= 14) {
                            continue;
                        }
                        $workflow->sign($document, $signers[0], $cycle->public_id);
                        if ($number <= 16) {
                            continue;
                        }
                        $workflow->sign($document, $signers[1], $cycle->public_id);
                        if ($number >= 19) {
                            $workflow->send($document, $number === 19 ? $owner : $signers[1], $cycle->public_id);
                        }
                    }
                    $this->command?->info($owner->email.': 20 dokumen selesai.');
                }
            });
        } catch (Throwable $exception) {
            foreach ($createdDirectories as $directory) {
                $disk->deleteDirectory($directory);
            }
            throw $exception;
        }

        foreach ($oldPaths as $path) {
            if (str_starts_with($path, 'signwork/') && ! str_contains($path, '..') && ! $disk->delete($path)) {
                $this->command?->warn('Ada file lama yang tidak dapat dihapus: '.$path);
            }
        }
        $this->command?->info('Selesai: admin dipertahankan; 10 user, 200 dokumen, 210 siklus. Password demo: SignWorkDemo!2026');
    }
}
