<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class UserProfileSeeder extends Seeder {
    /**
     * Dataset profil demo yang dibuat beragam lintas unit kerja dan jenjang.
     * Pemilihan profil bersifat pseudo-random tetapi stabil berdasarkan email user,
     * sehingga seeder tetap idempotent dan hasil tidak berubah setiap kali dijalankan.
     *
     * @var array<int, array{jabatan: string, unit_kerja: string, pangkat: string, golongan: string}>
     */
    private const PROFILE_POOL = [
        [
            'jabatan' => 'Pranata Komputer Ahli Pertama',
            'unit_kerja' => 'Dinas Komunikasi dan Informatika Kabupaten Subang',
            'pangkat' => 'Penata Muda Tk. I',
            'golongan' => 'III/b'
        ],
        [
            'jabatan' => 'Analis Keuangan Pusat dan Daerah Ahli Muda',
            'unit_kerja' => 'Badan Pendapatan Daerah Kabupaten Subang',
            'pangkat' => 'Penata',
            'golongan' => 'III/c'
        ],
        [
            'jabatan' => 'Administrator Kesehatan Ahli Muda',
            'unit_kerja' => 'Dinas Kesehatan Kabupaten Subang',
            'pangkat' => 'Penata Tk. I',
            'golongan' => 'III/d'
        ],
        [
            'jabatan' => 'Pranata Komputer Ahli Muda',
            'unit_kerja' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Subang',
            'pangkat' => 'Penata Tk. I',
            'golongan' => 'III/d'
        ],
        [
            'jabatan' => 'Penata Perizinan Ahli Pertama',
            'unit_kerja' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu Kabupaten Subang',
            'pangkat' => 'Penata Muda',
            'golongan' => 'III/a'
        ],
        [
            'jabatan' => 'Perencana Ahli Madya',
            'unit_kerja' => 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah Kabupaten Subang',
            'pangkat' => 'Pembina',
            'golongan' => 'IV/a'
        ],
        [
            'jabatan' => 'Analis Sumber Daya Manusia Aparatur Ahli Muda',
            'unit_kerja' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia Kabupaten Subang',
            'pangkat' => 'Penata Tk. I',
            'golongan' => 'III/d'
        ],
        [
            'jabatan' => 'Auditor Ahli Madya',
            'unit_kerja' => 'Inspektorat Daerah Kabupaten Subang',
            'pangkat' => 'Pembina',
            'golongan' => 'IV/a'
        ],
        [
            'jabatan' => 'Pengembang Teknologi Pembelajaran Ahli Pertama',
            'unit_kerja' => 'Dinas Pendidikan dan Kebudayaan Kabupaten Subang',
            'pangkat' => 'Penata Muda Tk. I',
            'golongan' => 'III/b'
        ],
        [
            'jabatan' => 'Perencana Ahli Muda',
            'unit_kerja' => 'Dinas Pekerjaan Umum dan Penataan Ruang Kabupaten Subang',
            'pangkat' => 'Penata',
            'golongan' => 'III/c'
        ]
    ];

    public function run(): void {
        if (!app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'UserProfileSeeder hanya untuk data local/testing agar tidak menimpa identitas pegawai riil.'
            );
        }

        $updated = 0;

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$updated): void {
                foreach ($users as $user) {
                    $profile = $this->profileFor($user);
                    $attributes = [];

                    if (!$user->isAdmin() && $this->hasLegacyDefaultProfile($user)) {
                        $attributes = $profile;
                    } else {
                        foreach ($profile as $key => $value) {
                            if (blank($user->getAttribute($key))) {
                                $attributes[$key] = $value;
                            }
                        }
                    }

                    if ($attributes === []) {
                        continue;
                    }

                    $user->forceFill($attributes)->save();
                    $updated++;
                }
            });

        $this->command?->info("User profile selesai diisi untuk {$updated} akun yang masih memiliki kolom kosong.");
    }

    private function hasLegacyDefaultProfile(User $user): bool {
        return $user->jabatan === 'Pelaksana' &&
            $user->unit_kerja === 'Dinas Komunikasi dan Informatika Kabupaten Subang' &&
            $user->pangkat === 'Penata Muda' &&
            $user->golongan === 'III/a';
    }

    /** @return array<string, string> */
    private function profileFor(User $user): array {
        $seed = mb_strtolower(trim((string) $user->email));
        $index = hexdec(substr(hash('sha256', $seed), 0, 8)) % count(self::PROFILE_POOL);
        $profile = self::PROFILE_POOL[$index];

        if ($user->isAdmin()) {
            return [
                'unit_kerja' => $profile['unit_kerja']
            ];
        }

        if (preg_match('/^sample(\d+)@demo\.signwork\.test$/', $seed, $matches)) {
            $number = max(1, (int) $matches[1]);

            return self::PROFILE_POOL[($number - 1) % count(self::PROFILE_POOL)];
        }

        return $profile;
    }
}
