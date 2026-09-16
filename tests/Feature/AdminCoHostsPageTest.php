<?php

namespace Tests\Feature;

use App\Filament\Resources\CoHosts\Pages\ListCoHosts;
use App\Models\Author;
use App\Models\CoHost;
use App\Models\Edition;
use App\Models\RegistrationFee;
use App\Models\User;
use App\Services\CoHostApproval;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Membuka halaman Pengajuan Co-host sebagai panitia sungguhan.
 *
 * Tes co-host yang ada hanya memanggil canAccess()/canCreate() dan tidak pernah
 * merender halamannya, sehingga halaman yang balas 500 tetap lolos.
 */
class AdminCoHostsPageTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::create(['name' => 'Super', 'email' => 'super-cohost-page@example.test', 'password' => 'secret-password']);
        $user->assignRole('superadmin');
        $this->actingAs($user, 'web');

        return $user;
    }

    private function coHost(string $email = 'pj@example.test', string $status = 'pending'): CoHost
    {
        $author = Author::create([
            'name' => 'Penanggung Jawab', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => 'cohost', 'registrant_category' => 'general',
        ]);

        return CoHost::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'institution_name' => 'Universitas Mitra',
            'institution_type' => 'university',
            'country' => 'ID',
            'pic_position' => 'Wakil Rektor',
            'status' => $status,
        ]);
    }

    private function partnershipFee(): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Kemitraan Co-host', 'en' => 'Co-host Partnership'],
            'audience' => 'cohost',
            'registrant_category' => 'general',
            'price_regular' => 5_000_000,
            'currency' => 'IDR',
        ]);
    }

    public function test_the_page_renders_when_there_is_nothing_yet(): void
    {
        $this->superadmin();

        Livewire::test(ListCoHosts::class)->assertOk();
    }

    public function test_the_page_renders_a_pending_application(): void
    {
        $this->superadmin();
        $coHost = $this->coHost();

        Livewire::test(ListCoHosts::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$coHost])
            ->assertSee('Universitas Mitra');
    }

    /** Baris yang sudah disetujui membawa voucher, invoice, dan sponsor sekaligus. */
    public function test_the_page_renders_an_approved_application(): void
    {
        Notification::fake();
        $this->superadmin();
        $this->partnershipFee();

        $coHost = app(CoHostApproval::class)->approve($this->coHost());

        // Tab bawaan hanya menampilkan yang menunggu tinjauan.
        Livewire::test(ListCoHosts::class)
            ->set('activeTab', 'approved')
            ->assertOk()
            ->assertCanSeeTableRecords([$coHost])
            ->assertSee($coHost->voucher->code);
    }

    public function test_the_page_renders_a_rejected_application(): void
    {
        $this->superadmin();
        $coHost = app(CoHostApproval::class)->reject($this->coHost(), 'Belum memenuhi syarat.');

        Livewire::test(ListCoHosts::class)
            ->set('activeTab', 'rejected')
            ->assertOk()
            ->assertCanSeeTableRecords([$coHost]);
    }

    /** Modal rincian merangkai data dari beberapa relasi sekaligus. */
    public function test_the_details_modal_opens(): void
    {
        Notification::fake();
        $this->superadmin();
        $this->partnershipFee();
        $coHost = app(CoHostApproval::class)->approve($this->coHost());

        Livewire::test(ListCoHosts::class)
            ->set('activeTab', 'approved')
            ->mountAction(TestAction::make('review')->table($coHost))
            ->assertOk();
    }

    public function test_an_application_can_be_approved_from_the_page(): void
    {
        Notification::fake();
        $this->superadmin();
        $this->partnershipFee();
        $coHost = $this->coHost();

        Livewire::test(ListCoHosts::class)
            ->callAction(TestAction::make('approve')->table($coHost));

        $this->assertSame('approved', $coHost->refresh()->status);
        $this->assertNotNull($coHost->voucher);
    }
}
