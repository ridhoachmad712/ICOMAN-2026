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
}
