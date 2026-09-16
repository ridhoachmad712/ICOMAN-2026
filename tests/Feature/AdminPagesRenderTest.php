<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Membuka SETIAP halaman daftar di panel admin sebagai panitia sungguhan.
 *
 * Dibuat setelah halaman Pengajuan Co-host membalas 500: kelas Tab-nya memakai
 * namespace Filament v3 yang tidak ada di v4, dan karena getTabs() baru
 * dipanggil dari blade, tidak ada satu pun tes yang menyentuhnya. Menyapu
 * seluruh resource sekaligus membuat kesalahan sejenis ketahuan di berkas ini,
 * bukan di produksi.
 */
class AdminPagesRenderTest extends TestCase
{
    public function test_every_admin_resource_list_page_opens(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-render@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $opened = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $page = $resource::getPages()['index'] ?? null;

            if (! $page || ! $resource::canAccess()) {
                continue;
            }

            $component = $page->getPage();

            // Kegagalan render muncul sebagai exception; pesannya menyebut
            // resource-nya supaya tidak perlu ditebak dari jejak tumpukan.
            try {
                Livewire::test($component)->assertOk();
            } catch (\Throwable $exception) {
                $this->fail($resource.' gagal dirender: '.$exception->getMessage());
            }

            $opened++;
        }

        // Kalau penemuan resource-nya rusak, sapuan ini diam-diam tidak menguji apa pun.
        $this->assertGreaterThan(10, $opened, 'Resource admin yang terbuka terlalu sedikit; penemuan resource mungkin rusak.');
    }
}
