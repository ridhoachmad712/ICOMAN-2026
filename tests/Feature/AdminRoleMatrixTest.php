<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Matriks peran panel admin. Sebelumnya admin konten bisa membuka naskah
 * akademik + data pembayaran, dan "Keputusan Review" (yang otomatis menerbitkan
 * LOA + email) tidak punya batasan peran sama sekali.
 */
class AdminRoleMatrixTest extends TestCase
{
    /** @return array<string, array<string, bool>> */
    private function expectations(): array
    {
        return [
            // resource => [role => boleh?]
            \App\Filament\Resources\Submissions\SubmissionResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false,
            ],
            \App\Filament\Resources\Registrations\RegistrationResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false,
            ],
            \App\Filament\Resources\RegistrationFees\RegistrationFeeResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false,
            ],
            \App\Filament\Resources\Topics\TopicResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => false,
            ],
            \App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => true,
            ],
            \App\Filament\Resources\News\NewsResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => true, 'reviewer' => false,
            ],
            \App\Filament\Resources\Authors\AuthorResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => false,
            ],
        ];
    }

    public function test_resource_access_follows_the_matrix(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $wrong = [];

        foreach ($this->expectations() as $resource => $roles) {
            foreach ($roles as $role => $expected) {
                $this->actingAs($this->userFor($role), 'web');
                if ($resource::canAccess() !== $expected) {
                    $wrong[] = class_basename($resource).' / '.$role.' seharusnya '.var_export($expected, true);
                }
            }
        }

        $this->assertSame([], $wrong, "Akses tidak sesuai matriks:\n".implode("\n", $wrong));
    }

    /** Tarif boleh dilihat admin registrasi, tetapi hanya superadmin yang boleh mengubah. */
    public function test_only_superadmin_can_change_fees(): void
    {
        $fees = \App\Filament\Resources\RegistrationFees\RegistrationFeeResource::class;

        $this->actingAs($this->userFor('admin_registrasi'), 'web');
        $this->assertTrue($fees::canAccess(), 'Admin registrasi harus bisa melihat tarif.');
        $this->assertFalse($fees::canCreate());
        $this->assertFalse($fees::canEdit(new \App\Models\RegistrationFee));
        $this->assertFalse($fees::canDelete(new \App\Models\RegistrationFee));

        $this->actingAs($this->userFor('superadmin'), 'web');
        $this->assertTrue($fees::canCreate());
    }

    /** Setiap peran harus punya minimal satu widget — dashboard kosong itu jalan buntu. */
    public function test_no_role_lands_on_an_empty_dashboard(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $panel = Filament::getPanel('admin');
        $empty = [];

        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer'] as $role) {
            $this->actingAs($this->userFor($role), 'web');
            $visible = array_filter($panel->getWidgets(), fn ($w) => $w::canView());
            if ($visible === []) {
                $empty[] = $role;
            }
        }

        $this->assertSame([], $empty, 'Peran tanpa widget: '.implode(', ', $empty));
    }

    /** Admin konten tidak boleh melihat angka pembayaran. */
    public function test_content_admin_cannot_see_payment_figures(): void
    {
        $this->actingAs($this->userFor('content_admin'), 'web');

        $this->assertFalse(\App\Filament\Widgets\RegistrationStats::canView());
        $this->assertFalse(\App\Filament\Widgets\LatestSubmissions::canView());
        // Statistik konten/acara tetap boleh.
        $this->assertTrue(\App\Filament\Widgets\OverviewStats::canView());
    }

    public function test_reviewer_gets_a_pending_review_widget_at_top_level(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userFor('reviewer'), 'web');

        $this->assertTrue(\App\Filament\Widgets\MyPendingReviews::canView());
        // Menu tunggal reviewer tidak disembunyikan di dalam dropdown.
        $this->assertNull(\App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource::getNavigationGroup());

        $this->actingAs($this->userFor('superadmin'), 'web');
        $this->assertSame('Submission', \App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource::getNavigationGroup());
    }

    private function userFor(string $role): User
    {
        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::firstOrCreate(
            ['email' => $role.'@matrix.test'],
            ['name' => $role, 'password' => 'secret-password'],
        );
        $user->syncRoles([$role]);

        return $user->refresh();
    }
}
