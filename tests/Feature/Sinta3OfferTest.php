<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Registrations\RegistrationResource;
use App\Filament\Resources\Submissions\Pages\EditSubmission;
use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kapan tawaran penerbitan SINTA 3 sampai ke author.
 *
 * `submissions.sinta3_offered` adalah satu-satunya saklar yang menentukan
 * apakah pilihan jurnal muncul di halaman pembayaran. Tes ini memetakan kapan
 * saklar itu menyala — dan kapan tidak, walaupun reviewer sudah
 * merekomendasikannya.
 */
class Sinta3OfferTest extends TestCase
{
    private Edition $edition;

    private Author $author;

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->author = Author::create([
            'name' => 'Penulis', 'email' => 'penulis-sinta@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $this->reviewer = User::create(['name' => 'Penilai', 'email' => 'penilai-sinta@example.test', 'password' => 'secret-password']);
    }

    private function paper(): Submission
    {
        return Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $this->author->id,
            'title' => 'Judul', 'abstract' => 'Isi.',
            'status' => 'extended_abstract_under_review',
        ]);
    }

    private function review(Submission $submission, bool $sinta3): Review
    {
        // Satu reviewer hanya boleh satu penugasan per fase, jadi tes yang
        // memerlukan dua penilaian memakai reviewer yang berbeda.
        $reviewer = $submission->reviewAssignments()->where('reviewer_id', $this->reviewer->id)->exists()
            ? User::create(['name' => 'Penilai Lain', 'email' => uniqid().'@example.test', 'password' => 'secret-password'])
            : $this->reviewer;

        $assignment = ReviewAssignment::create([
            'submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'phase' => 'extended_abstract',
            'assigned_at' => now(),
            'status' => 'completed',
        ]);

        return Review::create([
            'review_assignment_id' => $assignment->id,
            'score' => 90,
            'recommendation' => 'accept',
            'recommends_sinta3' => $sinta3,
        ]);
    }

    /** Urutan yang diharapkan: reviewer menilai, lalu panitia menerima. */
    public function test_a_recommendation_recorded_before_acceptance_reaches_the_author(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);

        $paper->changeStatus('accepted');

        $this->assertTrue($paper->fresh()->sinta3_offered);
    }

    public function test_no_recommendation_means_no_offer(): void
    {
        $paper = $this->paper();
        $this->review($paper, false);

        $paper->changeStatus('accepted');

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    /**
     * Reviewer kerap menyelesaikan penilaiannya setelah panitia menerima paper.
     * Rekomendasi seperti itu dulu hilang: saklarnya hanya dihitung sekali,
     * pada detik LOA pertama terbit.
     */
    public function test_a_recommendation_recorded_after_acceptance_still_opens_the_offer(): void
    {
        $paper = $this->paper();
        $paper->changeStatus('accepted');
        $this->assertFalse($paper->fresh()->sinta3_offered);

        $this->review($paper, true);

        $this->assertTrue($paper->fresh()->sinta3_offered);
    }

    /** Rekomendasi yang dicabut menutup tawarannya kembali. */
    public function test_withdrawing_the_recommendation_closes_the_offer(): void
    {
        $paper = $this->paper();
        $review = $this->review($paper, true);
        $paper->changeStatus('accepted');
        $this->assertTrue($paper->fresh()->sinta3_offered);

        $review->update(['recommends_sinta3' => false]);

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    public function test_deleting_the_review_closes_the_offer(): void
    {
        $paper = $this->paper();
        $review = $this->review($paper, true);
        $paper->changeStatus('accepted');

        $review->delete();

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    /** Satu reviewer merekomendasikan sudah cukup, walau yang lain tidak. */
    public function test_one_recommendation_among_several_is_enough(): void
    {
        $paper = $this->paper();
        $this->review($paper, false);
        $this->review($paper, true);

        $paper->changeStatus('accepted');

        $this->assertTrue($paper->fresh()->sinta3_offered);
    }

    // --- Keputusan panitia menang --------------------------------------------

    /**
     * Panitia boleh menutup tawaran yang direkomendasikan reviewer, dan
     * keputusan itu tidak boleh dibatalkan diam-diam oleh penilaian berikutnya.
     */
    public function test_a_committee_decision_survives_later_reviews(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $paper->fresh()->setSinta3Offer(false);

        $this->review($paper, true);

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    /** Begitu pula sebaliknya: dibuka panitia walau reviewer tidak merekomendasikan. */
    public function test_the_committee_can_open_an_offer_the_reviewers_did_not_recommend(): void
    {
        $paper = $this->paper();
        $this->review($paper, false);
        $paper->changeStatus('accepted');

        $paper->fresh()->setSinta3Offer(true);
        $this->review($paper, false);

        $this->assertTrue($paper->fresh()->sinta3_offered);
    }

    /** Paper yang tertinggal bisa dikenali: direkomendasikan, tapi belum ditawarkan. */
    public function test_a_missing_offer_can_be_spotted(): void
    {
        $paper = $this->paper();
        $paper->changeStatus('accepted');

        // Ditulis langsung ke database supaya melewati penyelarasan model,
        // meniru baris yang sudah ada sebelum perbaikan ini.
        $review = $this->review($paper, true);
        $paper->fresh()->forceFill(['sinta3_offered' => false])->save();

        $this->assertTrue($paper->fresh()->sinta3OfferIsMissing());

        $review->save();

        $this->assertFalse($paper->fresh()->sinta3OfferIsMissing());
    }

    // --- Perbaikan data lama --------------------------------------------------

    /** Menulis langsung ke database, meniru baris dari sebelum perbaikan ini. */
    private function stale(bool $recommended, bool $offered): Submission
    {
        $paper = $this->paper();
        $paper->changeStatus('accepted');
        $this->review($paper, $recommended);

        $paper->fresh()->forceFill(['sinta3_offered' => $offered])->save();

        return $paper->fresh();
    }

    /** Tanpa --fix, perintahnya hanya melaporkan. */
    public function test_the_command_reports_without_changing_anything(): void
    {
        $paper = $this->stale(recommended: true, offered: false);

        $this->artisan('icoman:refresh-sinta3')
            ->expectsOutputToContain('perlu diperbarui')
            ->assertSuccessful();

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    public function test_the_command_opens_the_offers_that_were_left_behind(): void
    {
        $paper = $this->stale(recommended: true, offered: false);

        $this->artisan('icoman:refresh-sinta3', ['--fix' => true])->assertSuccessful();

        $this->assertTrue($paper->fresh()->sinta3_offered);
    }

    public function test_the_command_closes_an_offer_no_reviewer_recommends(): void
    {
        $paper = $this->stale(recommended: false, offered: true);

        $this->artisan('icoman:refresh-sinta3', ['--fix' => true])->assertSuccessful();

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    /** Keputusan panitia tidak boleh ditimpa perintah perawatan. */
    public function test_the_command_leaves_a_committee_decision_alone(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');
        $paper->fresh()->setSinta3Offer(false);

        $this->artisan('icoman:refresh-sinta3', ['--fix' => true])
            ->expectsOutputToContain('dilewati karena tawarannya sudah ditetapkan panitia')
            ->assertSuccessful();

        $this->assertFalse($paper->fresh()->sinta3_offered);
    }

    public function test_the_command_says_so_when_everything_is_already_in_step(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $this->artisan('icoman:refresh-sinta3')
            ->expectsOutputToContain('sudah selaras')
            ->assertSuccessful();
    }

    // --- Terlihat di admin ----------------------------------------------------

    private function superadmin(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => uniqid().'@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        return $admin;
    }

    /**
     * Rekomendasi SINTA 3 tidak pernah dirender di panel admin, padahal itulah
     * yang membuka tawaran ke author — panitia menekan Accept tanpa melihatnya.
     */
    public function test_the_detail_page_shows_the_sinta3_recommendation(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $this->superadmin();

        Livewire::test(EditSubmission::class, ['record' => $paper->getRouteKey()])
            ->assertOk()
            ->assertSee('Rekomendasi SINTA 3')
            ->assertSee('Terbuka untuk author');
    }

    public function test_the_detail_page_flags_an_offer_that_never_opened(): void
    {
        $paper = $this->stale(recommended: true, offered: false);

        $this->superadmin();

        Livewire::test(EditSubmission::class, ['record' => $paper->getRouteKey()])
            ->assertOk()
            ->assertSee('belum dibuka');
    }

    /** Panitia bisa membuka tawaran yang tertinggal langsung dari daftarnya. */
    public function test_the_committee_can_open_the_offer_from_the_list(): void
    {
        $paper = $this->stale(recommended: true, offered: false);

        $this->superadmin();

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'all')
            ->callAction(TestAction::make('sinta3Offer')->table($paper));

        $paper = $paper->fresh();
        $this->assertTrue($paper->sinta3_offered);
        $this->assertNotNull($paper->sinta3_offer_overridden_at);
    }

    // --- Terlihat di halaman pembayaran author --------------------------------

    /** Ujungnya: tawaran yang terbuka berarti author benar-benar bisa memilih. */
    public function test_an_open_offer_puts_the_choice_on_the_payment_page(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $registration = $this->invoiceFor($paper);
        $this->actingAs($this->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertSee('SINTA 3 journal', escape: false);
    }

    public function test_a_closed_offer_leaves_the_payment_page_without_the_choice(): void
    {
        $paper = $this->paper();
        $this->review($paper, false);
        $paper->changeStatus('accepted');

        $registration = $this->invoiceFor($paper);
        $this->actingAs($this->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertDontSee('SINTA 3 journal', escape: false);
    }

    private function invoiceFor(Submission $paper): Registration
    {
        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Presenter', 'en' => 'Presenter'],
            'audience' => 'presenter',
            'registrant_category' => 'general',
            'price_regular' => 750_000,
            'currency' => 'IDR',
        ]);

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $this->author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $paper->id,
            'payment_method' => 'gateway',
            'amount' => 750_000,
            'pricing_snapshot' => $fee->quote(),
            'status' => 'pending',
        ]);
    }

    // --- Diagnosa -------------------------------------------------------------

    public function test_the_diagnosis_names_a_missing_paper_link(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $registration = $this->invoiceFor($paper);
        // Invoice yang tidak terhubung ke paper: pilihan jurnal mustahil muncul.
        $registration->forceFill(['submission_id' => null])->save();

        $this->artisan('icoman:diagnose-invoice', ['invoice' => $registration->id])
            ->expectsOutputToContain('TIDAK terhubung ke paper')
            ->expectsOutputToContain('punya paper yang sudah diterima')
            ->assertSuccessful();
    }

    public function test_the_diagnosis_explains_a_closed_offer(): void
    {
        $paper = $this->paper();
        $this->review($paper, false);
        $paper->changeStatus('accepted');

        $registration = $this->invoiceFor($paper);

        $this->artisan('icoman:diagnose-invoice', ['invoice' => $registration->id])
            ->expectsOutputToContain('Tawaran SINTA 3 terbuka pada papernya')
            ->assertSuccessful();
    }

    public function test_the_diagnosis_reports_a_healthy_invoice(): void
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        $registration = $this->invoiceFor($paper);

        $this->artisan('icoman:diagnose-invoice', ['invoice' => $registration->id])
            ->expectsOutputToContain($paper->submission_number)
            ->assertSuccessful();
    }

    public function test_the_diagnosis_refuses_an_unknown_invoice(): void
    {
        $this->artisan('icoman:diagnose-invoice', ['invoice' => 999999])
            ->expectsOutputToContain('tidak ditemukan')
            ->assertFailed();
    }

    // --- Penguncian oleh pembayaran yang menggantung --------------------------

    /** Order yang masih hidup di gateway: total tidak boleh berubah. */
    private function liveOrder(Registration $registration, ?string $expiresAt): void
    {
        $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'kasera',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-X',
            'gateway_payment_id' => 'payreq_x'.$registration->id,
            'amount' => $registration->amount,
            'status' => 'initiated',
            'raw_response' => $expiresAt ? ['id' => 'payreq_x', 'expires_at' => $expiresAt] : ['id' => 'payreq_x'],
        ]);
    }

    private function offeredInvoice(): Registration
    {
        $paper = $this->paper();
        $this->review($paper, true);
        $paper->changeStatus('accepted');

        return $this->invoiceFor($paper);
    }

    /**
     * Pilihan jurnal memang dikunci selama ada order hidup — mengubah total saat
     * halaman bayar sudah terbuka akan menagih angka yang berbeda dari invoicenya.
     * Yang dulu hilang adalah keterangannya: panelnya lenyap tanpa penjelasan.
     */
    public function test_a_live_order_locks_the_choice_and_says_why(): void
    {
        $registration = $this->offeredInvoice();
        $this->liveOrder($registration, now()->addHour()->toIso8601String());

        $this->actingAs($this->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertSee('locked', escape: false)
            ->assertSee('Check Payment Status', escape: false)
            ->assertDontSee(route('author.registration.journal', $registration), escape: false);
    }

    /**
     * Order yang masa berlakunya habis tidak lagi mengunci apa pun. Kalau ikut
     * mengunci, satu percobaan bayar yang ditinggalkan menutup pilihan jurnal
     * selamanya.
     */
    public function test_an_expired_order_stops_locking_the_choice(): void
    {
        $registration = $this->offeredInvoice();
        $this->liveOrder($registration, now()->subHour()->toIso8601String());

        $this->assertFalse($registration->fresh()->hasUnresolvedPayment());

        $this->actingAs($this->author, 'author');

        $this->get(RegistrationResource::getUrl('view', ['record' => $registration], panel: 'author'))
            ->assertOk()
            ->assertSee(route('author.registration.journal', $registration), escape: false);
    }

    /** Tanpa tanggal kedaluwarsa dari gateway, order dianggap masih hidup. */
    public function test_an_order_without_an_expiry_still_locks(): void
    {
        $registration = $this->offeredInvoice();
        $this->liveOrder($registration, null);

        $this->assertTrue($registration->fresh()->hasUnresolvedPayment());
    }

    /** Pembayaran yang berhasil tetap mengunci, sekedaluwarsa apa pun ordernya. */
    public function test_a_successful_payment_keeps_locking(): void
    {
        $registration = $this->offeredInvoice();
        $registration->payments()->create([
            'method' => 'gateway', 'gateway_name' => 'kasera',
            'gateway_reference' => 'ICOMAN-lunas', 'gateway_payment_id' => 'payreq_lunas',
            'amount' => $registration->amount, 'status' => 'success',
            'raw_response' => ['expires_at' => now()->subDay()->toIso8601String()],
        ]);

        $this->assertTrue($registration->fresh()->hasUnresolvedPayment());
    }
}
