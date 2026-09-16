<?php

namespace Tests\Feature;

use App\Filament\Resources\CoHosts\Pages\ListCoHosts;
use App\Filament\Resources\RegistrationFees\Pages\CreateRegistrationFee;
use App\Models\Author;
use App\Models\CoHost;
use App\Models\Edition;
use App\Models\PageSection;
use App\Models\RegistrationFee;
use App\Models\User;
use App\Services\CoHostApproval;
use App\Services\SectionContent;
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

    /**
     * Menyetujui tanpa tarif kemitraan menyuruh panitia menambahkannya dengan
     * audience "cohost" — jadi pilihan itu harus benar-benar ada di formnya.
     * Sebelumnya tidak, sehingga pesannya menunjuk ke tempat yang buntu.
     */
    public function test_the_fee_form_offers_the_cohost_audience_the_message_names(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-fee@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        Livewire::test(CreateRegistrationFee::class)
            ->assertOk()
            ->fillForm([
                'edition_id' => $this->edition->id,
                'audience' => 'cohost',
                'category.en' => 'Co-host Partnership',
                'category.id' => 'Kemitraan Co-host',
                'price_regular' => 5_000_000,
                'currency' => 'IDR',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('registration_fees', [
            'edition_id' => $this->edition->id,
            'audience' => 'cohost',
        ]);

        // Dan dengan tarif itu ada, persetujuan berjalan.
        Notification::fake();
        $coHost = $this->coHost('pj-fee@example.test');
        app(CoHostApproval::class)->approve($coHost);

        $this->assertSame('approved', $coHost->refresh()->status);
    }

    /**
     * Biaya kemitraan adalah urusan panitia dan institusi, bukan harga bagi
     * pengunjung. Blok tarif yang ada memang sudah menyaring per audience
     * sendiri, jadi yang diuji di sini adalah sumber datanya — supaya blok
     * baru yang menampilkan seluruh tarif tidak ikut membocorkannya.
     */
    public function test_the_partnership_fee_is_left_out_of_the_public_fee_records(): void
    {
        $partnership = $this->partnershipFee();
        $public = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Peserta Seminar', 'en' => 'Seminar Attendee'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 250_000,
            'currency' => 'IDR',
        ]);

        $section = PageSection::create(['target' => 'home', 'type' => 'fees', 'order' => 0]);
        $records = app(SectionContent::class)->records($section);

        $this->assertTrue($records->contains('id', $public->id));
        $this->assertFalse($records->contains('id', $partnership->id));
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
