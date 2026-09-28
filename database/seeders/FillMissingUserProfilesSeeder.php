<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FillMissingUserProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $examples = [
            ['jabatan' => 'Pranata Komputer Ahli Pertama', 'unit_kerja' => 'Dinas Komunikasi dan Informatika (Data Demo)', 'pangkat' => 'Penata Muda', 'golongan' => 'III/a'],
            ['jabatan' => 'Analis Kebijakan Ahli Muda', 'unit_kerja' => 'Sekretariat Daerah (Data Demo)', 'pangkat' => 'Penata', 'golongan' => 'III/c'],
            ['jabatan' => 'Arsiparis Ahli Pertama', 'unit_kerja' => 'Dinas Kearsipan dan Perpustakaan (Data Demo)', 'pangkat' => 'Penata Muda Tingkat I', 'golongan' => 'III/b'],
            ['jabatan' => 'Pengelola Layanan Operasional', 'unit_kerja' => 'Dinas Pendidikan dan Kebudayaan (Data Demo)', 'pangkat' => 'Pengatur', 'golongan' => 'II/c'],
        ];
        $updated = 0;
        User::query()->select('id')->chunkById(100, function (Collection $users) use ($examples, &$updated): void {
            foreach ($users as $record) {
                DB::transaction(function () use ($record, $examples, &$updated): void {
                    $user = User::query()->lockForUpdate()->find($record->id);
                    if (! $user) {
                        return;
                    }
                    $example = $examples[($user->id - 1) % count($examples)];
                    foreach ($example as $field => $value) {
                        if (trim((string) $user->getAttribute($field)) === '') {
                            $user->setAttribute($field, $value);
                        }
                    }
                    if ($user->isDirty()) {
                        $user->save();
                        $updated++;
                    }
                });
            }
        });
        $this->command?->info("Profil dilengkapi: {$updated} pengguna. Isian merupakan data contoh; sesuaikan sebelum penggunaan nyata.");
    }
}
