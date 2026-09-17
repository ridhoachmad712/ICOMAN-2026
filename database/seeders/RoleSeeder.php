<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Role admin panel (guard `web`).
     * - superadmin       : akses penuh + user management + site settings.
     * - admin_registrasi : kelola pendaftaran peserta, pembayaran, bukti transfer & naskah.
     * - reviewer         : hanya lihat & isi review paper yang ditugaskan.
     * - content_admin    : kelola konten informasi & publikasi.
     * - bendahara        : mengawasi uang. Membaca seluruh transaksi dan
     *                      mencatat pembayaran yang tidak terkabar gateway.
     *                      Tidak menyentuh naskah, tarif, voucher, pengaturan.
     */
    public function run(): void
    {
        foreach (['superadmin', 'admin_registrasi', 'reviewer', 'content_admin', 'bendahara'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
