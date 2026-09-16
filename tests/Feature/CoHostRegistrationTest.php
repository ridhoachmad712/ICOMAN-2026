<?php

namespace Tests\Feature;

use App\Filament\Resources\CoHosts\CoHostResource;
use App\Models\Author;
use App\Models\CoHost;
use App\Models\Edition;
use App\Models\PageSection;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\CoHostApproved;
use App\Services\CoHostApproval;
use App\Services\VoucherRedeemer;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur pendaftaran institusi co-host.
 *
 * Bentuknya mengikuti pendaftaran peserta, dengan satu perbedaan pokok: satu
 * co-host berarti beberapa paper gratis, jadi pengajuannya tidak langsung jadi
 * — panitia meninjau dulu, dan vouchernya baru menyala setelah biaya
 * kemitraannya lunas.
 */
class CoHostRegistrationTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function partnershipFee(int $price = 5_000_000): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Kemitraan Co-host', 'en' => 'Co-host Partnership'],
            'audience' => 'cohost',
            'registrant_category' => 'general',
            'price_regular' => $price,
            'currency' => 'IDR',
            'order' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function application(array $overrides = []): array
    {
        return array_merge([
            'institution_name' => 'Universitas Mitra',
            'institution_type' => 'university',
            'website' => 'https://mitra.example.test',
            'name' => 'Dr Penanggung Jawab',
            'pic_position' => 'Wakil Rektor',
            'email' => 'mitra@example.test',
            'phone' => '08123456789',
            'country' => 'ID',
            'password' => 'rahasia-sekali',
            'password_confirmation' => 'rahasia-sekali',
        ], $overrides);
    }

    private function apply(array $overrides = []): CoHost
    {
        $before = CoHost::count();

        // Rute pendaftaran hanya untuk tamu; pemohon sebelumnya sudah masuk.
        auth('author')->logout();

        $this->withSession(['author_terms_ok' => 'cohost'])
            ->post(route('author.register.cohost'), $this->application($overrides))
            // Tanpa ini, pengajuan yang ditolak validasi tetap membalas redirect
            // dan tesnya diam-diam memakai pengajuan sebelumnya.
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($before + 1, CoHost::count(), 'Pengajuan tidak tersimpan.');

        return CoHost::latest('id')->firstOrFail();
    }

    private function superadmin(string $email = 'super-cohost@example.test'): User
    {
        Role::findOrCreate('superadmin', 'web');
        $user = User::create(['name' => 'Super', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole('superadmin');

        return $user;
    }

    // --- Pengajuan ----------------------------------------------------------

    public function test_the_role_chooser_offers_a_co_host_path(): void
    {
        $this->get(route('author.register'))->assertOk()->assertSee('Co-host', escape: false);
    }

    public function test_the_co_host_terms_have_their_own_clauses(): void
    {
        $this->get(route('author.register.terms', ['role' => 'cohost']))
            ->assertOk()
            ->assertSee('Free paper quota')
            ->assertSee('Partnership fee');
    }

    /** Formulir hanya terbuka setelah syaratnya disetujui, seperti jalur peserta. */
    public function test_the_form_is_closed_until_the_terms_are_accepted(): void
    {
        $this->get(route('author.register.start', ['role' => 'cohost']))
            ->assertRedirect(route('author.register.terms', ['role' => 'cohost']));

        $this->withSession(['author_terms_ok' => 'cohost'])
            ->get(route('author.register.start', ['role' => 'cohost']))
            ->assertOk()
            ->assertSee('Institution name', escape: false);
    }

    public function test_an_application_creates_a_pending_record_and_an_account(): void
    {
        $coHost = $this->apply();

        $this->assertSame('pending', $coHost->status);
        $this->assertSame('Universitas Mitra', $coHost->institution_name);
        $this->assertSame('cohost', $coHost->author->participation_type);
        $this->assertTrue($coHost->author->isCoHost());
        // Belum ada apa-apa yang diberikan sebelum panitia menyetujui.
        $this->assertNull($coHost->voucher_id);
        $this->assertNull($coHost->registration());
    }

    public function test_the_applicant_is_signed_in_and_can_see_the_status(): void
    {
        $coHost = $this->apply();

        $this->assertTrue(auth('author')->check());
        $this->assertSame($coHost->author_id, auth('author')->id());
    }

    public function test_an_application_without_accepting_the_terms_is_turned_back(): void
    {
        $this->post(route('author.register.cohost'), $this->application())
            ->assertRedirect(route('author.register.terms', ['role' => 'cohost']));

        $this->assertSame(0, CoHost::count());
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        Author::create([
            'name' => 'Sudah Ada', 'email' => 'mitra@example.test', 'password' => 'secret-password',
            'participation_type' => 'participant', 'registrant_category' => 'general',
        ]);

        $this->withSession(['author_terms_ok' => 'cohost'])
            ->post(route('author.register.cohost'), $this->application())
            ->assertSessionHasErrors('email');

        $this->assertSame(0, CoHost::count());
    }

    // --- Peninjauan panitia -------------------------------------------------

    public function test_approval_issues_a_voucher_an_invoice_and_a_partner_listing(): void
    {
        Notification::fake();
        $fee = $this->partnershipFee();
        $coHost = $this->apply();

        $approved = app(CoHostApproval::class)->approve($coHost, $this->superadmin()->id);

        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->voucher);
        $this->assertSame(CoHost::FREE_PAPERS, $approved->voucher->quota);
        // Voucher belum menyala: biaya kemitraannya belum dibayar.
        $this->assertFalse($approved->voucher->is_active);

        $invoice = $approved->registration();
        $this->assertNotNull($invoice);
        $this->assertSame($fee->id, $invoice->registration_fee_id);
        $this->assertSame('5000000.00', $invoice->amount);

        $this->assertNotNull($approved->sponsor);
        $this->assertSame('partner', $approved->sponsor->tier);
        $this->assertFalse($approved->sponsor->is_published);

        Notification::assertSentTo($approved->author, CoHostApproved::class);
    }

    /** Tanpa tarif kemitraan, panitia tidak punya angka untuk ditagihkan. */
    public function test_approval_is_held_back_until_the_partnership_fee_exists(): void
    {
        $coHost = $this->apply();

        $this->expectException(ValidationException::class);
        app(CoHostApproval::class)->approve($coHost);
    }

    public function test_approving_twice_does_not_issue_two_vouchers(): void
    {
        Notification::fake();
        $this->partnershipFee();
        $coHost = $this->apply();

        $service = app(CoHostApproval::class);
        $service->approve($coHost);
        $service->approve($coHost->fresh());

        $this->assertSame(1, Voucher::count());
        $this->assertSame(1, Registration::count());
    }

    public function test_rejection_records_the_reason(): void
    {
        $coHost = $this->apply();

        $rejected = app(CoHostApproval::class)->reject($coHost, 'Belum memenuhi syarat kemitraan.', $this->superadmin()->id);

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Belum memenuhi syarat kemitraan.', $rejected->rejection_reason);
        $this->assertNull($rejected->voucher_id);
    }

    // --- Pembayaran menyalakan kemitraan ------------------------------------

    /**
     * Inti aturannya: voucher dan pencantuman sebagai partner baru hidup setelah
     * invoice kemitraan lunas — lewat jalur pembayaran mana pun.
     */
    public function test_paying_the_fee_activates_the_voucher_and_the_listing(): void
    {
        Notification::fake();
        $this->partnershipFee();
        $coHost = app(CoHostApproval::class)->approve($this->apply());

        $coHost->registration()->update(['status' => 'paid', 'paid_at' => now()]);

        $coHost->refresh();
        $this->assertTrue($coHost->voucher->refresh()->is_active);
        $this->assertTrue($coHost->sponsor->refresh()->is_published);
        $this->assertTrue($coHost->isActive());
    }

    /** Sebelum lunas, kodenya memang belum bisa dipakai penulis. */
    public function test_the_code_is_refused_while_the_fee_is_unpaid(): void
    {
        Notification::fake();
        $this->partnershipFee();
        $coHost = app(CoHostApproval::class)->approve($this->apply());

        $presenter = Author::create([
            'name' => 'Penulis Mitra', 'email' => 'penulis@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $paperFee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Presenter', 'en' => 'Presenter'],
            'audience' => 'presenter', 'registrant_category' => 'general',
            'price_regular' => 400_000, 'currency' => 'IDR',
        ]);
        $submission = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $presenter->id,
            'title' => 'Judul', 'abstract' => 'Isi.', 'status' => 'accepted', 'loa_issued_at' => now(),
        ]);
        $invoice = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $presenter->id,
            'registration_fee_id' => $paperFee->id, 'submission_id' => $submission->id,
            'payment_method' => 'gateway', 'amount' => 400_000,
            'pricing_snapshot' => $paperFee->quote(), 'status' => 'pending',
        ]);

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->redeem($invoice, $coHost->voucher->code);
    }

    // --- Tampilan publik ----------------------------------------------------

    /** Hanya kemitraan yang sudah berjalan penuh yang tampil di website. */
    public function test_only_active_co_hosts_appear_on_the_public_list(): void
    {
        Notification::fake();
        $this->partnershipFee();

        $waiting = app(CoHostApproval::class)->approve($this->apply(['institution_name' => 'Belum Bayar', 'email' => 'belum@example.test']));
        $active = app(CoHostApproval::class)->approve($this->apply(['institution_name' => 'Sudah Bayar', 'email' => 'sudah@example.test']));
        $active->registration()->update(['status' => 'paid', 'paid_at' => now()]);

        PageSection::create(['target' => 'home', 'type' => 'cohosts', 'order' => 0]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Sudah Bayar', $html);
        $this->assertStringNotContainsString('Belum Bayar', $html);
    }

    // --- Kewenangan ---------------------------------------------------------

    public function test_reviewers_cannot_reach_the_applications(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('reviewer', 'web');
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer-cohost@example.test', 'password' => 'secret-password']);
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'web');

        $this->assertFalse(CoHostResource::canAccess());
    }

    public function test_applications_are_never_created_from_the_admin_panel(): void
    {
        $this->assertFalse(CoHostResource::canCreate());
    }
}
