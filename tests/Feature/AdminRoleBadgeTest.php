<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Kolom pencarian global diganti badge peran yang sedang login. */
class AdminRoleBadgeTest extends TestCase
{
    public function test_global_search_is_disabled(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());
    }

    public function test_each_role_has_its_own_label_and_colour(): void
    {
        $expected = [
            'superadmin' => ['Super Admin', 'danger'],
            'admin_registrasi' => ['Admin Registrasi', 'info'],
            'content_admin' => ['Admin Konten', 'warning'],
            'reviewer' => ['Reviewer', 'success'],
        ];

        $colours = [];
        foreach ($expected as $role => [$label, $colour]) {
            $badge = $this->userWithRole($role)->roleBadge();

            $this->assertSame($label, $badge['label'], "Label peran [{$role}] tidak sesuai.");
            $this->assertSame($colour, $badge['color'], "Warna peran [{$role}] tidak sesuai.");
            $colours[] = $badge['color'];
        }

        // Tiap peran harus benar-benar berbeda warnanya.
        $this->assertSame($colours, array_unique($colours));
    }

    public function test_a_user_without_a_role_gets_a_neutral_badge(): void
    {
        $user = User::create(['name' => 'Nobody', 'email' => 'nobody@example.test', 'password' => 'secret-password']);

        $this->assertSame(['label' => 'Tanpa Peran', 'color' => 'gray'], $user->roleBadge());
    }

    public function test_multiple_roles_show_the_highest_one(): void
    {
        $user = $this->userWithRole('reviewer');
        Role::findOrCreate('superadmin', 'web');
        $user->assignRole('superadmin');

        $this->assertSame('Super Admin', $user->refresh()->roleBadge()['label']);
    }

    public function test_the_topbar_shows_the_role_not_the_username(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = $this->userWithRole('superadmin', 'Ridho Achmad');

        $this->actingAs($user, 'web')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Super Admin')
            // Kolom pencarian global tidak lagi dirender.
            ->assertDontSee('wire:model.live.debounce.500ms="search"', false);
    }

    private function userWithRole(string $role, string $name = 'Admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::create([
            'name' => $name,
            'email' => $role.'-badge@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
