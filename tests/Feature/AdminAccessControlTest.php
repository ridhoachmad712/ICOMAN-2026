<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationFees\RegistrationFeeResource;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    /** Tarif registrasi menentukan nominal tagihan — hanya superadmin yang boleh membukanya. */
    public function test_registration_fees_are_superadmin_only(): void
    {
        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $allowed = ['superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => false];

        foreach ($allowed as $role => $expected) {
            $user = User::create([
                'name' => ucfirst($role),
                'email' => $role.'@example.test',
                'password' => 'secret-password',
            ]);
            $user->assignRole($role);

            $this->actingAs($user, 'web');

            $this->assertSame(
                $expected,
                RegistrationFeeResource::canAccess(),
                "Akses Registration Fees untuk role [{$role}] tidak sesuai harapan.",
            );
        }
    }

    /**
     * sinta3_fee dipakai untuk menambah total invoice presenter, jadi harus bisa
     * diatur dari halaman Site Settings (sebelumnya tidak punya field sama sekali).
     */
    public function test_superadmin_can_set_the_sinta3_additional_fee_from_site_settings(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');

        $admin = User::create([
            'name' => 'Super',
            'email' => 'super-settings@example.test',
            'password' => 'secret-password',
        ]);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        \Livewire\Livewire::test(\App\Filament\Pages\Settings\ManageSiteSettings::class)
            ->assertOk()
            ->fillForm(['sinta3_fee' => 300000])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(300000, app(\App\Settings\SiteSettings::class)->sinta3_fee);
    }
}
