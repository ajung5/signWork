<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

/**
 * Hapus seluruh data aplikasi dan pertahankan semua akun admin.
 *
 * Seeder ini sengaja tidak dipanggil oleh DatabaseSeeder. Jalankan secara
 * eksplisit agar penghapusan data tidak terjadi setiap kali seeding biasa.
 */
class ClearAllDataExceptAdminsSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private array $paths = [];

    /**
     * @var array<int, string>
     */
    private array $documentDirectories = [];

    public function run(): void
    {
        $this->assertSafeEnvironment();

        $adminCount = DB::table('users')
            ->whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])
            ->count();

        if ($adminCount === 0) {
            throw new LogicException(
                'Tidak ada akun admin. Tidak ada data yang dihapus.'
            );
        }

        $removedUserIds = DB::table('users')
            ->whereNotIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])
            ->pluck('id');
        $removedUserEmails = DB::table('users')
            ->whereIn('id', $removedUserIds)
            ->pluck('email');

        $this->collectDocumentFiles();

        $counts = [
            'users' => $removedUserIds->count(),
            'documents' => $this->countTable('documents'),
            'cycles' => $this->countTable('document_cycles'),
            'workflow_entries' => $this->countTable('workflow_master_entries'),
            'activity_logs' => $this->countTable('activity_logs'),
        ];

        DB::transaction(function () use ($removedUserIds, $removedUserEmails): void {
            // Hapus tabel anak terlebih dahulu agar tetap aman bila FK tidak
            // memakai cascade pada instalasi database yang sudah lama.
            $this->deleteTable('document_signature_steps');
            $this->deleteTable('document_approval_steps');
            $this->deleteTable('document_cycles');
            $this->deleteTable('documents');

            // Versi schema lama masih mungkin memiliki tabel ini.
            $this->deleteTable('document_workflow_settings');
            $this->deleteTable('workflow_master_entries');
            $this->deleteTable('activity_logs');

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->whereIn('user_id', $removedUserIds)
                    ->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')
                    ->whereIn('email', $removedUserEmails)
                    ->delete();
            }

            DB::table('users')
                ->whereIn('id', $removedUserIds)
                ->delete();
        });

        $this->removeStoredFiles();

        $this->command?->info(sprintf(
            'Selesai: %d admin dipertahankan; %d user, %d dokumen, %d siklus, %d master workflow, dan %d log dihapus.',
            $adminCount,
            $counts['users'],
            $counts['documents'],
            $counts['cycles'],
            $counts['workflow_entries'],
            $counts['activity_logs'],
        ));
    }

    private function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'Pembersihan data hanya boleh pada APP_ENV=local atau testing.'
            );
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            throw new RuntimeException(
                'Schema users.role belum tersedia. Pulihkan migration aplikasi terlebih dahulu; jangan menjalankan migrate:fresh pada checkout terbaru yang migration aplikasinya terhapus.'
            );
        }

        $requiredTables = [
            'documents',
            'document_cycles',
            'document_approval_steps',
            'document_signature_steps',
            'workflow_master_entries',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            throw new RuntimeException(
                'Schema aplikasi belum lengkap. Tabel yang hilang: '.implode(', ', $missingTables).'. Pulihkan migration aplikasi terlebih dahulu.'
            );
        }
    }

    private function collectDocumentFiles(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'uuid')) {
            DB::table('documents')
                ->select('uuid')
                ->whereNotNull('uuid')
                ->pluck('uuid')
                ->each(function (string $uuid): void {
                    $this->documentDirectories[] = 'signwork/'.$uuid;
                });
        }

        if (Schema::hasTable('document_cycles')) {
            $columns = array_values(array_filter(
                ['original_path', 'prepared_path', 'current_path', 'source_path'],
                fn (string $column): bool => Schema::hasColumn('document_cycles', $column),
            ));

            if ($columns !== []) {
                DB::table('document_cycles')
                    ->select($columns)
                    ->get()
                    ->each(function (object $cycle) use ($columns): void {
                        foreach ($columns as $column) {
                            $this->paths[] = $cycle->{$column};
                        }
                    });
            }
        }

        if (
            Schema::hasTable('document_signature_steps')
            && Schema::hasColumn('document_signature_steps', 'output_path')
        ) {
            DB::table('document_signature_steps')
                ->whereNotNull('output_path')
                ->pluck('output_path')
                ->each(function (string $path): void {
                    $this->paths[] = $path;
                });
        }

        $this->paths = collect($this->paths)
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
        $this->documentDirectories = collect($this->documentDirectories)
            ->unique()
            ->values()
            ->all();
    }

    private function removeStoredFiles(): void
    {
        $disk = Storage::disk('local');

        foreach ($this->paths as $path) {
            if (
                str_starts_with($path, 'signwork/')
                && ! str_contains($path, '..')
                && ! $disk->delete($path)
            ) {
                $this->command?->warn('File tidak dapat dihapus: '.$path);
            }
        }

        foreach ($this->documentDirectories as $directory) {
            if (
                str_starts_with($directory, 'signwork/')
                && ! str_contains($directory, '..')
            ) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    private function deleteTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->delete();
        }
    }

    private function countTable(string $table): int
    {
        return Schema::hasTable($table)
            ? DB::table($table)->count()
            : 0;
    }
}
