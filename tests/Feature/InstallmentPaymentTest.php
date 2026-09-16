<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Services\KaseraGateway;
use App\Services\KaseraService;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cicilan dua tahap untuk presenter mahasiswa.
 *
 * Harga presenter mahasiswa 350.000, boleh dibayar sekaligus atau dua tahap:
 * 200.000 lebih dulu, sisanya sebelum tenggat pelunasan. Yang disimpan panitia
 * hanya nominal cicilan pertama — sisanya selalu dihitung dari total, sehingga
 * dua cicilan tidak pernah bisa berjumlah salah.
 */
class InstallmentPaymentTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        config()->set('services.kasera.api_key', 'kp_test_key');
    }

    private function fee(string $audience = 'presenter', string $category = 'student_s1', ?int $first = 200_000, int $price = 350_000, ?int $firstSinta3 = 350_000): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Presenter Mahasiswa', 'en' => 'Student Presenter'],
            'audience' => $audience,
            'registrant_category' => $category,
            'price_regular' => $price,
            'installment_first_amount' => $first,
            'installment_first_amount_sinta3' => $firstSinta3,
            'currency' => 'IDR',
        ]);
    }

    /** Invoice presenter yang memilih penerbitan SINTA 3: 350.000 + 300.000. */
    private function sinta3Registration(RegistrationFee $fee): Registration
    {
        $settings = app(SiteSettings::class);
        $settings->sinta3_fee = 300_000;
        $settings->save();

        $registration = $this->registration($fee);
        $price = $registration->priceDetails();
        $price['addon_amount'] = 300_000;
        $price['quoted_addon_amount'] = 300_000;
        $price['journal_target'] = 'sinta3';

        $registration->update([
            'amount' => (float) $price['base_amount'] + 300_000,
            'pricing_snapshot' => $price,
        ]);

        return $registration->refresh();
    }

    private function registration(RegistrationFee $fee): Registration
    {
        $author = Author::create([
            'name' => 'Mahasiswa', 'email' => uniqid().'@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'student_s1',
        ]);

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'payment_method' => 'gateway',
            'amount' => $fee->price_regular,
            'pricing_snapshot' => $fee->quote(),
            'status' => 'pending',
        ]);
    }

    /** @param array<int, array<string, mixed>> $captured */
    private function mockGateway(array &$captured): void
    {
        $this->app->bind(KaseraGateway::class, function () use (&$captured) {
            return new class($captured) extends KaseraGateway
            {
                public function __construct(private array &$captured) {}

                public function createTransaction(string $apiKey, array $body, string $idempotencyKey): array
                {
                    $this->captured[] = $body;

                    return ['id' => 'payreq_'.count($this->captured), 'checkout_url' => 'https://pay.kasera.id/p/x'.count($this->captured)];
                }
            };
        });
    }

    private function pay(Registration $registration): void
    {
        $payment = $registration->payments()->where('status', 'initiated')->latest('id')->firstOrFail();

        app(KaseraService::class)->applyEvent([
            'id' => 'evt_'.$payment->id,
            'type' => 'payment.paid',
            'data' => [
                'payment_request_id' => $payment->gateway_payment_id,
                'amount' => (int) $payment->amount,
                'currency' => 'IDR',
                'paid_at' => now()->toIso8601String(),
            ],
        ]);
    }

    // --- Kelayakan ----------------------------------------------------------

    public function test_only_a_student_presenter_fee_allows_instalments(): void
    {
        $this->assertTrue($this->fee()->allowsInstallments());
        $this->assertFalse($this->fee('presenter', 'general')->allowsInstallments());
        $this->assertFalse($this->fee('participant', 'student_s1')->allowsInstallments());
    }

    /** Tanpa nominal dari panitia, tidak ada cicilan yang ditawarkan. */
    public function test_a_fee_without_a_first_instalment_cannot_be_split(): void
    {
        $this->assertFalse($this->fee(first: null)->allowsInstallments());
        $this->assertFalse($this->registration($this->fee(first: null))->allowsInstallments());
    }

    /** Cicilan pertama tidak boleh sama dengan atau melebihi total. */
    public function test_a_first_instalment_that_is_not_smaller_than_the_total_is_refused(): void
    {
        $this->assertFalse($this->fee(first: 350_000)->allowsInstallments());
        $this->assertFalse($this->fee(first: 400_000)->allowsInstallments());
    }

    public function test_the_offer_disappears_once_something_has_been_paid(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        $this->assertTrue($registration->allowsInstallments());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $this->assertFalse($registration->refresh()->allowsInstallments());
    }

    // --- Nominal ------------------------------------------------------------

    /**
     * Inti aturannya: cicilan kedua adalah sisa, bukan angka kedua yang
     * disimpan terpisah — jadi 200.000 + sisa selalu tepat 350.000.
     */
    public function test_the_two_instalments_add_up_to_the_full_amount(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $service = app(KaseraService::class);

        $service->createCheckoutRedirect($registration, installment: true);
        $this->assertSame(200000, $captured[0]['amount']);
        $this->pay($registration);

        $service->createCheckoutRedirect($registration->refresh());
        $this->assertSame(150000, $captured[1]['amount']);

        $this->assertSame(350000, $captured[0]['amount'] + $captured[1]['amount']);
        $this->assertSame((int) $registration->amount, $captured[0]['amount'] + $captured[1]['amount']);
    }

    /** Membayar sekaligus tetap menagih seluruhnya. */
    public function test_paying_in_full_charges_the_whole_amount(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration);

        $this->assertSame(350000, $captured[0]['amount']);
        $this->assertFalse($registration->refresh()->installment_plan);
    }

    /**
     * Author yang mencoba bayar lunas, mengurungkan, lalu memilih mencicil harus
     * ditagih 200.000 — bukan 350.000 dari percobaan sebelumnya.
     *
     * Order yang masih "initiated" sengaja dipakai ulang supaya tab kedua tidak
     * membuka tagihan kedua; sebelum ini, pemakaian ulang itu tidak memeriksa
     * nominalnya, sehingga pilihan cicilan tetap diarahkan ke halaman bayar
     * 350.000 yang lama.
     */
    public function test_switching_to_instalments_charges_the_first_instalment(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->post(route('author.registration.pay', $registration))->assertRedirect();
        $this->assertSame(350000, $captured[0]['amount']);

        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment'])->assertRedirect();

        $this->assertCount(2, $captured);
        $this->assertSame(200000, $captured[1]['amount'], 'Cicilan pertama harus ditagih 200.000 di Kasera Pay.');

        $registration->refresh();
        $this->assertTrue($registration->installment_plan);
        $this->assertSame(200000.0, $registration->amountDueNow());
    }

    /** Dan sebaliknya: berpindah ke bayar lunas harus menagih seluruhnya. */
    public function test_switching_back_to_paying_in_full_charges_the_whole_amount(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment'])->assertRedirect();
        $this->assertSame(200000, $captured[0]['amount']);

        $this->post(route('author.registration.pay', $registration))->assertRedirect();

        $this->assertSame(350000, $captured[1]['amount']);
    }

    /** Order yang ditinggalkan dilepas, jadi tidak ada dua tagihan hidup sekaligus. */
    public function test_the_abandoned_order_is_released(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->post(route('author.registration.pay', $registration));
        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment']);

        $this->assertSame(1, $registration->payments()->where('status', 'initiated')->count());
        $this->assertSame(200000.0, (float) $registration->payments()->where('status', 'initiated')->value('amount'));
    }

    /** Tab kedua dengan pilihan yang sama tetap tidak boleh membuka tagihan kedua. */
    public function test_a_second_tab_with_the_same_choice_reuses_the_order(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment']);
        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment']);

        $this->assertCount(1, $captured);
        $this->assertSame(1, $registration->payments()->count());
    }

    // --- Pilihan jurnal mengubah pembagiannya ---------------------------------

    /**
     * Paper SINTA 3 ditagih 650.000, jadi cicilannya 350.000 + 300.000 — bukan
     * 200.000 + 450.000 yang akan keluar dari satu angka tetap.
     */
    public function test_a_sinta3_paper_uses_its_own_split(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->sinta3Registration($this->fee());
        $service = app(KaseraService::class);

        $this->assertSame(650000.0, (float) $registration->amount);

        $service->createCheckoutRedirect($registration, installment: true);
        $this->assertSame(350000, $captured[0]['amount']);
        $this->pay($registration);

        $service->createCheckoutRedirect($registration->refresh());
        $this->assertSame(300000, $captured[1]['amount']);

        $this->assertSame(650000, $captured[0]['amount'] + $captured[1]['amount']);

        $this->pay($registration);
        $this->assertSame('paid', $registration->refresh()->status);
    }

    /** Paper reguler tetap 200.000 + 150.000. */
    public function test_a_regular_paper_keeps_its_own_split(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);

        $this->assertSame(200000, $captured[0]['amount']);
    }

    /**
     * Author boleh mengubah pilihan jurnalnya sebelum membayar, dan cicilan
     * pertamanya harus ikut berubah — bukan menagih 200.000 atas tagihan
     * 650.000.
     */
    public function test_choosing_sinta3_after_picking_instalments_moves_the_first_amount(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $fee = $this->fee();
        $registration = $this->registration($fee);

        $this->assertSame(200000.0, $registration->firstInstallmentAmount());

        $upgraded = $this->sinta3Registration($fee);

        $this->assertSame(350000.0, $upgraded->firstInstallmentAmount());
    }

    /** Tanpa angka SINTA 3, angka regulernya dipakai — pembagiannya tetap tepat. */
    public function test_without_a_sinta3_amount_the_regular_one_is_used(): void
    {
        $registration = $this->sinta3Registration($this->fee(firstSinta3: null));

        $this->assertSame(200000.0, $registration->firstInstallmentAmount());
        $this->assertSame(450000.0, (float) $registration->amount - $registration->firstInstallmentAmount());
    }

    /** Halaman invoice menyebut angka SINTA 3, bukan angka regulernya. */
    public function test_the_invoice_page_shows_the_sinta3_split(): void
    {
        $registration = $this->sinta3Registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertSee('350.000')
            ->assertSee('300.000');
    }

    // --- Status -------------------------------------------------------------

    /**
     * Cicilan pertama tidak melunasi registrasi. Ini yang menjaga gerbang
     * unggah full paper tetap tertutup sampai seluruhnya dibayar.
     */
    public function test_the_first_instalment_does_not_settle_the_registration(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $registration->refresh();
        $this->assertSame('pending', $registration->status);
        $this->assertNull($registration->paid_at);
        $this->assertTrue($registration->isPartiallyPaid());
        $this->assertSame(200000.0, $registration->paidAmount());
        $this->assertSame(150000.0, $registration->outstandingAmount());
    }

    public function test_the_second_instalment_settles_it(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $service = app(KaseraService::class);

        $service->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);
        $service->createCheckoutRedirect($registration->refresh());
        $this->pay($registration);

        $registration->refresh();
        $this->assertSame('paid', $registration->status);
        $this->assertNotNull($registration->paid_at);
        $this->assertFalse($registration->isPartiallyPaid());
        $this->assertSame(0.0, $registration->outstandingAmount());
    }

    /** Presenter yang baru mencicil separuh belum boleh mengunggah full paper. */
    public function test_a_half_paid_registration_does_not_open_the_full_paper_upload(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $submission = Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $registration->author_id,
            'title' => 'Judul', 'abstract' => 'Isi.',
            'status' => 'accepted', 'loa_issued_at' => now(),
        ]);
        $registration->update(['submission_id' => $submission->id]);

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $this->assertFalse($submission->refresh()->canSubmitFullPaper());
    }

    /** Cicilan kedua yang gagal tidak boleh menghapus jejak yang pertama. */
    public function test_a_failed_second_instalment_keeps_the_first_one(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());
        $service = app(KaseraService::class);

        $service->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);
        $service->createCheckoutRedirect($registration->refresh());

        $second = $registration->payments()->where('status', 'initiated')->latest('id')->firstOrFail();
        $service->applyTransaction(['id' => $second->gateway_payment_id, 'status' => 'expired', 'amount' => (int) $second->amount, 'currency' => 'IDR']);

        $registration->refresh();
        // 'failed' akan menyiratkan tidak ada uang yang masuk, padahal ada.
        $this->assertSame('pending', $registration->status);
        $this->assertSame(200000.0, $registration->paidAmount());
        $this->assertSame(150000.0, $registration->amountDueNow());
    }

    // --- Tenggat ------------------------------------------------------------

    public function test_the_settlement_deadline_comes_from_the_important_dates(): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'kind' => 'installment',
            'label' => ['en' => 'Instalment settlement', 'id' => 'Pelunasan cicilan'],
            'date' => now()->addDays(30)->toDateString(),
        ]);

        $registration = $this->registration($this->fee());

        $this->assertNotNull($registration->installmentDueAt());
        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $registration->installmentDueAt()->toDateString(),
        );
    }

    public function test_an_unsettled_instalment_is_flagged_overdue_after_the_deadline(): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'kind' => 'installment',
            'label' => ['en' => 'Instalment settlement', 'id' => 'Pelunasan cicilan'],
            'date' => now()->subDay()->toDateString(),
        ]);

        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        // Belum membayar apa pun: belum bisa disebut menunggak.
        $this->assertFalse($registration->isInstallmentOverdue());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $this->assertTrue($registration->refresh()->isInstallmentOverdue());
    }

    /** Tenggat yang lewat menandai, tapi tidak mencabut apa pun sendiri. */
    public function test_an_overdue_instalment_is_only_flagged(): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'kind' => 'installment',
            'label' => ['en' => 'Instalment settlement', 'id' => 'Pelunasan cicilan'],
            'date' => now()->subDay()->toDateString(),
        ]);

        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $registration->refresh();
        $this->assertSame('pending', $registration->status);
        $this->assertSame(150000.0, $registration->amountDueNow());
    }

    // --- Jalur author -------------------------------------------------------

    public function test_the_invoice_page_offers_both_ways_to_pay(): void
    {
        $registration = $this->registration($this->fee());
        $this->actingAs($registration->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertSee('Pay First Instalment', escape: false)
            ->assertSee('Pay in Full', escape: false)
            ->assertSee('Choose how to pay', escape: false);
    }

    /** Presenter non-mahasiswa tidak boleh ditawari cicilan. */
    public function test_the_invoice_page_hides_instalments_from_everyone_else(): void
    {
        $fee = $this->fee('presenter', 'general', first: null);
        $author = Author::create([
            'name' => 'Dosen', 'email' => 'dosen@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $registration = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'registration_fee_id' => $fee->id, 'payment_method' => 'gateway',
            'amount' => $fee->price_regular, 'pricing_snapshot' => $fee->quote(), 'status' => 'pending',
        ]);

        $this->actingAs($author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertDontSee('Pay First Instalment', escape: false);
    }

    // --- Panitia -------------------------------------------------------------

    /**
     * Cicilan tidak mengubah status registrasi, jadi tanpa kolomnya sendiri
     * panitia hanya melihat "pending" dan tidak tahu ada 200.000 yang masuk.
     */
    public function test_the_admin_list_shows_the_instalment_state(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $this->asRegistrationAdmin();

        Livewire::test(ListRegistrations::class)
            ->assertOk()
            ->assertSee('Cicilan 1/2')
            ->assertSee('150.000');
    }

    /** Menunggak ditandai, bukan sekadar terlihat sama dengan yang lain. */
    public function test_the_admin_list_marks_an_overdue_instalment(): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'kind' => 'installment',
            'label' => ['en' => 'Instalment settlement', 'id' => 'Pelunasan cicilan'],
            'date' => now()->subDay()->toDateString(),
        ]);

        $captured = [];
        $this->mockGateway($captured);
        $registration = $this->registration($this->fee());

        app(KaseraService::class)->createCheckoutRedirect($registration, installment: true);
        $this->pay($registration);

        $this->asRegistrationAdmin();

        Livewire::test(ListRegistrations::class)
            ->assertOk()
            ->assertSee('Lewat tenggat');
    }

    /** Filter "Menunggak cicilan" hanya menampilkan yang belum lunas. */
    public function test_the_overdue_filter_leaves_out_settled_registrations(): void
    {
        $captured = [];
        $this->mockGateway($captured);
        $service = app(KaseraService::class);

        $halfPaid = $this->registration($this->fee());
        $service->createCheckoutRedirect($halfPaid, installment: true);
        $this->pay($halfPaid);

        $settled = $this->registration($this->fee());
        $service->createCheckoutRedirect($settled, installment: true);
        $this->pay($settled);
        $service->createCheckoutRedirect($settled->refresh());
        $this->pay($settled);

        $this->assertSame('paid', $settled->refresh()->status);
        $this->asRegistrationAdmin();

        Livewire::test(ListRegistrations::class)
            ->filterTable('installment_overdue')
            ->assertCanSeeTableRecords([$halfPaid])
            ->assertCanNotSeeTableRecords([$settled]);
    }

    /**
     * Kelayakan diperiksa ulang di server. Kalau hanya tombolnya yang
     * disembunyikan, siapa pun bisa mengirim plan=installment sendiri.
     */
    private function asRegistrationAdmin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin Reg', 'email' => uniqid().'@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');
    }

    public function test_an_ineligible_author_cannot_force_the_instalment_plan(): void
    {
        $captured = [];
        $this->mockGateway($captured);

        $fee = $this->fee('presenter', 'general', first: null);
        $author = Author::create([
            'name' => 'Dosen', 'email' => 'dosen2@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $registration = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'registration_fee_id' => $fee->id, 'payment_method' => 'gateway',
            'amount' => $fee->price_regular, 'pricing_snapshot' => $fee->quote(), 'status' => 'pending',
        ]);

        $this->actingAs($author, 'author');
        $this->post(route('author.registration.pay', $registration), ['plan' => 'installment'])->assertRedirect();

        $this->assertFalse($registration->refresh()->installment_plan);
        $this->assertSame(350000, $captured[0]['amount']);
    }
}
