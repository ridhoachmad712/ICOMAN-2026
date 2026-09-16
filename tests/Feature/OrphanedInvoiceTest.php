<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Services\RegistrationProvisioner;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Invoice presenter yang kehilangan tautan papernya.
 *
 * `registrations.submission_id` memakai nullOnDelete, jadi paper yang dihapus
 * lalu dikirim ulang meninggalkan invoicenya tanpa paper. Invoice seperti itu
 * tidak akan pernah menampilkan pilihan Jurnal SINTA 3 — tawarannya melekat
 * pada paper — dan membiarkannya berarti menerbitkan tagihan kedua untuk orang
 * yang sama.
 */
class OrphanedInvoiceTest extends TestCase
{
    private Edition $edition;

    private Author $author;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->author = Author::create([
            'name' => 'Mahasiswa', 'email' => 'mahasiswa-yatim@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'student_s1',
        ]);

        RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Presenter Mahasiswa', 'en' => 'Undergraduate student'],
            'audience' => 'presenter',
            'registrant_category' => 'student_s1',
            'price_regular' => 350_000,
            'installment_first_amount' => 200_000,
            'currency' => 'IDR',
        ]);
    }

    private function acceptedPaper(): Submission
    {
        $paper = Submission::create([
            'edition_id' => $this->edition->id,
            'author_id' => $this->author->id,
            'title' => 'Judul', 'abstract' => 'Isi.',
            'status' => 'extended_abstract_under_review',
        ]);
        $paper->changeStatus('accepted');

        return $paper->fresh();
    }

    /** Paper dihapus lalu dikirim ulang — persis jalan yang menyisakan invoice yatim. */
    private function orphan(): Registration
    {
        $first = $this->acceptedPaper();
        $registration = app(RegistrationProvisioner::class)->ensureFor($this->author);
        $this->assertNotNull($registration);
        $this->assertSame($first->id, $registration->submission_id);

        $first->delete();

        return $registration->fresh();
    }

    public function test_deleting_the_paper_leaves_the_invoice_without_one(): void
    {
        $orphan = $this->orphan();

        $this->assertNull($orphan->submission_id, 'submission_id memakai nullOnDelete.');
        $this->assertNotNull($orphan->registrationFee, 'Tarifnya tetap tarif presenter.');
    }

    /**
     * Saat author melewati checkout lagi, invoice yatimnya diambil alih —
     * bukan ditinggal mati sambil menerbitkan tagihan kedua.
     */
    public function test_the_next_checkout_adopts_the_orphan_instead_of_billing_twice(): void
    {
        $orphan = $this->orphan();
        $replacement = $this->acceptedPaper();

        $adopted = app(RegistrationProvisioner::class)->ensureFor($this->author);

        $this->assertSame($orphan->id, $adopted->id, 'Invoice yang sama dipakai ulang.');
        $this->assertSame($replacement->id, $adopted->submission_id);
        $this->assertSame(1, Registration::where('author_id', $this->author->id)->count());
    }

    /** Yang sudah lunas tidak boleh dipindahkan: paper baru akan langsung terhitung terbayar. */
    public function test_a_paid_orphan_is_left_alone(): void
    {
        $orphan = $this->orphan();
        $orphan->update(['status' => 'paid', 'paid_at' => now()]);

        $replacement = $this->acceptedPaper();
        $fresh = app(RegistrationProvisioner::class)->ensureFor($this->author);

        $this->assertNotSame($orphan->id, $fresh->id);
        $this->assertNull($orphan->fresh()->submission_id);
        $this->assertSame($replacement->id, $fresh->submission_id);
    }

    /** Invoice peserta seminar memang tidak punya paper; jangan ikut diambil. */
    public function test_a_seminar_attendee_invoice_is_not_adopted(): void
    {
        $attendee = Author::create([
            'name' => 'Peserta', 'email' => 'peserta-yatim@example.test', 'password' => 'secret-password',
            'participation_type' => 'participant', 'registrant_category' => 'general',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['id' => 'Peserta', 'en' => 'Attendee'],
            'audience' => 'participant', 'registrant_category' => 'general',
            'price_regular' => 250_000, 'currency' => 'IDR',
        ]);
        $invoice = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $attendee->id,
            'registration_fee_id' => $fee->id, 'payment_method' => 'gateway',
            'amount' => 250_000, 'pricing_snapshot' => $fee->quote(), 'status' => 'pending',
        ]);

        $this->artisan('icoman:relink-invoices', ['--fix' => true])->assertSuccessful();

        $this->assertNull($invoice->fresh()->submission_id);
    }

    // --- Perintah perbaikan ---------------------------------------------------

    public function test_the_command_reports_without_changing_anything(): void
    {
        $orphan = $this->orphan();
        $this->acceptedPaper();

        $this->artisan('icoman:relink-invoices')
            ->expectsOutputToContain('bisa disambungkan')
            ->assertSuccessful();

        $this->assertNull($orphan->fresh()->submission_id);
    }

    public function test_the_command_relinks_the_orphan(): void
    {
        $orphan = $this->orphan();
        $replacement = $this->acceptedPaper();

        $this->artisan('icoman:relink-invoices', ['--fix' => true])->assertSuccessful();

        $this->assertSame($replacement->id, $orphan->fresh()->submission_id);
    }

    /** Tanpa paper pengganti, invoicenya disebut dan diserahkan ke panitia. */
    public function test_the_command_flags_an_orphan_it_cannot_resolve(): void
    {
        $orphan = $this->orphan();

        $this->artisan('icoman:relink-invoices', ['--fix' => true])
            ->expectsOutputToContain('tidak ada paper diterima yang belum punya invoice')
            ->assertSuccessful();

        $this->assertNull($orphan->fresh()->submission_id);
    }

    /**
     * Paper yang sudah punya invoicenya sendiri tidak boleh diambil: kalau
     * diambil, satu paper dipegang dua invoice dan salah satunya jadi tagihan
     * ganda untuk author yang sama.
     */
    public function test_the_command_does_not_steal_a_paper_that_already_has_an_invoice(): void
    {
        $orphan = $this->orphan();

        // Invoice papernya dibuat langsung: lewat checkout, invoice yatimnya
        // justru akan diambil alih, sehingga keadaan ini tidak pernah terbentuk.
        $replacement = $this->acceptedPaper();
        $ownInvoice = Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $this->author->id,
            'registration_fee_id' => $orphan->registration_fee_id,
            'submission_id' => $replacement->id,
            'payment_method' => 'gateway',
            'amount' => 350_000,
            'status' => 'pending',
        ]);

        $this->artisan('icoman:relink-invoices', ['--fix' => true])->assertSuccessful();

        $this->assertNull($orphan->fresh()->submission_id, 'Invoice yatimnya tidak boleh ikut mengambil paper itu.');
        $this->assertSame($replacement->id, $ownInvoice->fresh()->submission_id);
    }

    public function test_the_command_says_so_when_there_is_nothing_to_do(): void
    {
        $this->acceptedPaper();
        app(RegistrationProvisioner::class)->ensureFor($this->author);

        $this->artisan('icoman:relink-invoices')
            ->expectsOutputToContain('Tidak ada invoice presenter yang kehilangan')
            ->assertSuccessful();
    }
}
