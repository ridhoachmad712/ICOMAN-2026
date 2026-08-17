<?php

namespace Database\Seeders;

use App\Models\Edition;
use Illuminate\Database\Seeder;

class EditionSeeder extends Seeder
{
    /**
     * 1 edition aktif agar sistem langsung bisa dipakai (query publik di-scope ke edition aktif).
     * Tanggal & tema dikosongkan — diisi panitia via admin, bukan data dummy permanen.
     */
    public function run(): void
    {
        Edition::updateOrCreate(
            ['name' => 'ICOMAN 2026'],
            [
                'theme' => null,
                'start_date' => null,
                'end_date' => null,
                'is_active' => true,
            ],
        );
    }
}
