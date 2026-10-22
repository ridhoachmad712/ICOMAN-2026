<?php

namespace Tests\Feature;

use App\Filament\Resources\Submissions\Pages\ListSubmissions;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BorderpayService;
use App\Services\RegistrationProvisioner;
use App\Services\VoucherRedeemer;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Author hanya memilih jurnal bila paper ditawari SINTA 3. Yang tidak
 * ditawari otomatis Reguler. Yang ditawari wajib memilih sebelum membayar,
 * dan aturan itu dijaga di server, bukan hanya dengan menyembunyikan tombol.
 */
class JournalChoiceBeforePaymentTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        RegistrationFee::create([
            'edition_id' => $this->edition->id, 'category' => ['en' => 'Presenter'],
            'audience' => 'presenter', 'registrant_category' => 'general',
            'price_regular' => 400_000, 'currency' => 'IDR',
        ]);
    }

    public function test_the_effective_journal_follows_the_offer(): void
    {
        $notOffered = $this->paper(offered: false);
        $offered = $this->paper(offered: true);
        $chosen = $this->paper(offered: true, chosen: 'sinta3');

        $this->assertSame('regular', $notOffered->effectiveJournalTarget());
        $this->assertFalse($notOffered->awaitsJournalChoice());
        $this->assertNull($offered->effectiveJournalTarget());
        $this->assertTrue($offered->awaitsJournalChoice());
        $this->assertSame('sinta3', $chosen->effectiveJournalTarget());

        $ids = fn (string $target) => Submission::query()->withEffectiveJournal($target)->pluck('id')->all();
        $this->assertSame([$notOffered->id], $ids('regular'));
        $this->assertSame([$chosen->id], $ids('sinta3'));
        $this->assertSame([$offered->id], $ids('unchosen'));
    }

    public function test_the_table_labels_papers_without_an_offer_as_regular_and_flags_paid_gaps(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin_registrasi', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-journal@example.test', 'password' => 'secret-password']);
        $admin->assignRole('admin_registrasi');
        $this->actingAs($admin, 'web');

        $notOffered = $this->paper(offered: false);
        $offeredUnpaid = $this->paper(offered: true);
        $offeredPaid = $this->paper(offered: true);
        $this->invoice($offeredPaid)->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        Livewire::test(ListSubmissions::class)
            ->set('activeTab', 'all')
            ->assertTableColumnStateSet('journal_target', 'Jurnal Reguler', $notOffered)
            ->assertTableColumnStateSet('journal_target', 'Belum dipilih', $offeredUnpaid)
            ->assertTableColumnStateSet('journal_target', 'Belum dipilih (sudah lunas)', $offeredPaid)
            ->filterTable('journal_target', 'regular')
            ->assertCanSeeTableRecords([$notOffered])
            ->assertCanNotSeeTableRecords([$offeredUnpaid, $offeredPaid]);
    }

    public function test_checkout_is_refused_until_the_journal_is_chosen(): void
    {
        $invoice = $this->invoice($this->paper(offered: true));

        try {
            app(BorderpayService::class)->createCheckoutRedirect($invoice);
            $this->fail('Checkout seharusnya ditolak sebelum jurnal dipilih.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_a_voucher_that_would_settle_the_invoice_waits_for_the_journal_choice(): void
    {
        $invoice = $this->invoice($this->paper(offered: true));
        $this->voucher('LUNAS-2026', 400_000);
        $this->voucher('HEMAT-2026', 100_000);

        // Voucher penuh akan melunasi invoice sebelum jurnal dipilih: ditolak.
        try {
            app(VoucherRedeemer::class)->redeem($invoice, 'LUNAS-2026');
            $this->fail('Voucher penuh seharusnya ditolak sebelum jurnal dipilih.');
        } catch (ValidationException $exception) {
            $this->assertMatchesRegularExpression('/jurnal|journal/i', implode(' ', $exception->errors()['voucher_code']));
        }
        $this->assertNull($invoice->refresh()->voucher_id);

        // Potongan sebagian tetap boleh, seperti alur semula.
        app(VoucherRedeemer::class)->redeem($invoice, 'HEMAT-2026');
        $this->assertSame('pending', $invoice->refresh()->status);
        $this->assertNotNull($invoice->voucher_id);
    }

    private function voucher(string $code, int $value): Voucher
    {
        return Voucher::create([
            'edition_id' => $this->edition->id, 'code' => $code, 'host_name' => 'Promo',
            'type' => 'discount', 'audience' => 'all', 'discount_kind' => 'fixed',
            'discount_value' => $value, 'quota' => 5, 'is_active' => true,
        ]);
    }

    private function paper(bool $offered, ?string $chosen = null): Submission
    {
        $author = Author::create([
            'name' => 'Presenter', 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        return Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id, 'title' => 'Paper',
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
            'sinta3_offered' => $offered,
            'journal_target' => $chosen ?? 'regular',
            'journal_target_chosen_at' => $chosen ? now() : null,
        ]);
    }

    private function invoice(Submission $submission): Registration
    {
        $invoice = app(RegistrationProvisioner::class)->ensureFor($submission->author);
        $this->assertNotNull($invoice, 'Invoice presenter tidak terbentuk.');

        return $invoice;
    }
}
