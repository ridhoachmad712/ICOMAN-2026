<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\ImportantDates\ImportantDateResource;
use App\Filament\Resources\RegistrationFees\RegistrationFeeResource;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Menu admin: Dashboard, Website, Submission, Conference, Pengaturan. */
class AdminNavigationTest extends TestCase
{
    /** Bar navigasi hanya berisi Dashboard + grup-grup ini. */
    private const GROUPS = ['Website', 'Submission', 'Conference', 'Pengaturan'];

    public function test_admin_navigation_has_exactly_three_groups_beside_dashboard(): void
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
            if ($page === Dashboard::class) {
                continue;
            }
            $group = $page::getNavigationGroup();
            if (! in_array($group, self::GROUPS, true)) {
                $stray[] = class_basename($page).' => '.var_export($group, true);
            }
        }

        $this->assertSame([], $stray, "Menu di luar kelompok yang ditetapkan:\n".implode("\n", $stray));
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

    public function test_key_entries_live_in_the_expected_menu(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superadmin(), 'web');

        $this->assertSame('Submission', AuthorResource::getNavigationGroup());
        $this->assertSame('Submission', RegistrationResource::getNavigationGroup());
        $this->assertSame('Submission', RegistrationFeeResource::getNavigationGroup());
        $this->assertSame('Website', SpeakerResource::getNavigationGroup());
        $this->assertSame('Website', ImportantDateResource::getNavigationGroup());
    }

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-nav@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');

        return $admin;
    }
}
