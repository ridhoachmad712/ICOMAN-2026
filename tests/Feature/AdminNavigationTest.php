<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Menu admin diringkas jadi 5 kelompok, dan kartu akun di dashboard dihapus. */
class AdminNavigationTest extends TestCase
{
    private const GROUPS = [
        'Submission & Review',
        'Peserta & Pembayaran',
        'Acara & Narasumber',
        'Konten Website',
        'Pengaturan',
    ];

    public function test_admin_navigation_has_exactly_five_groups(): void
    {
        $panel = Filament::getPanel('admin');

        $declared = array_map(
            fn ($group) => is_string($group) ? $group : $group->getLabel(),
            array_values($panel->getNavigationGroups()),
        );

        $this->assertSame(self::GROUPS, $declared);
    }

    public function test_every_admin_resource_and_page_sits_in_one_of_those_groups(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $panel = Filament::getPanel('admin');
        $stray = [];

        foreach ($panel->getResources() as $resource) {
            $group = $resource::getNavigationGroup();
            if (! in_array($group, self::GROUPS, true)) {
                $stray[] = class_basename($resource).' => '.var_export($group, true);
            }
        }

        foreach ($panel->getPages() as $page) {
            // Dashboard memang berdiri sendiri tanpa grup.
            if ($page === \Filament\Pages\Dashboard::class) {
                continue;
            }
            $group = $page::getNavigationGroup();
            if (! in_array($group, self::GROUPS, true)) {
                $stray[] = class_basename($page).' => '.var_export($group, true);
            }
        }

        $this->assertSame([], $stray, "Menu di luar 5 kelompok:\n".implode("\n", $stray));
    }

    public function test_the_dashboard_account_card_is_gone(): void
    {
        $this->assertNotContains(AccountWidget::class, Filament::getPanel('admin')->getWidgets());
    }

    public function test_the_admin_dashboard_still_renders(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($this->superadmin(), 'web')
            ->get('/admin')
            ->assertOk();
    }

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-nav@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');

        return $admin;
    }
}
