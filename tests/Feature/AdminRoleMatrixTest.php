<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\RegistrationFees\RegistrationFeeResource;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Topics\TopicResource;
use App\Filament\Widgets\LatestSubmissions;
use App\Filament\Widgets\MyPendingReviews;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\RegistrationStats;
use App\Models\RegistrationFee;
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
            SubmissionResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false, 'bendahara' => false,
            ],
            RegistrationResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false, 'bendahara' => true,
            ],
            // Buku transaksi: alasan utama peran bendahara ada.
            PaymentResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false, 'bendahara' => true,
            ],
            RegistrationFeeResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false, 'bendahara' => false,
            ],
            TopicResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => false, 'bendahara' => false,
            ],
            ReviewAssignmentResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => false, 'reviewer' => true, 'bendahara' => false,
            ],
            NewsResource::class => [
                'superadmin' => true, 'admin_registrasi' => false, 'content_admin' => true, 'reviewer' => false, 'bendahara' => false,
            ],
            AuthorResource::class => [
                'superadmin' => true, 'admin_registrasi' => true, 'content_admin' => false, 'reviewer' => false, 'bendahara' => false,
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
        $fees = RegistrationFeeResource::class;

        $this->actingAs($this->userFor('admin_registrasi'), 'web');
        $this->assertTrue($fees::canAccess(), 'Admin registrasi harus bisa melihat tarif.');
        $this->assertFalse($fees::canCreate());
        $this->assertFalse($fees::canEdit(new RegistrationFee));
        $this->assertFalse($fees::canDelete(new RegistrationFee));

        $this->actingAs($this->userFor('superadmin'), 'web');
        $this->assertTrue($fees::canCreate());
    }

    /** Setiap peran harus punya minimal satu widget — dashboard kosong itu jalan buntu. */
    public function test_no_role_lands_on_an_empty_dashboard(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $panel = Filament::getPanel('admin');
        $empty = [];

        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer', 'bendahara'] as $role) {
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

        $this->assertFalse(RegistrationStats::canView());
        $this->assertFalse(LatestSubmissions::canView());
        // Statistik konten/acara tetap boleh.
        $this->assertTrue(OverviewStats::canView());
    }

    public function test_reviewer_gets_a_pending_review_widget_at_top_level(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->userFor('reviewer'), 'web');

        $this->assertTrue(MyPendingReviews::canView());
        // Menu tunggal reviewer tidak disembunyikan di dalam dropdown.
        $this->assertNull(ReviewAssignmentResource::getNavigationGroup());

        $this->actingAs($this->userFor('superadmin'), 'web');
        $this->assertSame('Submission', ReviewAssignmentResource::getNavigationGroup());
    }

    private function userFor(string $role): User
    {
        foreach (['superadmin', 'admin_registrasi', 'content_admin', 'reviewer', 'bendahara'] as $r) {
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
