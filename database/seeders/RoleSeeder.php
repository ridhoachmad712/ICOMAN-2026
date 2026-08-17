<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Role admin panel (guard `web`).
     * - superadmin   : akses penuh + user management + site settings.
     * - content_admin : CRUD konten, tanpa user management/settings.
     * - reviewer      : hanya lihat & isi review paper yang ditugaskan (Fase 4).
     */
    public function run(): void
    {
        foreach (['superadmin', 'content_admin', 'reviewer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
