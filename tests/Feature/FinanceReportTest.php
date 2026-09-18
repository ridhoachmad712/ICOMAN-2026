<?php

namespace Tests\Feature;

use App\Filament\Pages\Finance\FinanceReport;
use App\Filament\Pages\Finance\Widgets\FinanceStats;
use App\Filament\Pages\Finance\Widgets\OutstandingInvoices;
use App\Filament\Pages\Finance\Widgets\RevenueByCategory;
use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use App\Support\FinanceSummary;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rekap keuangan.
 *
 * Yang diuji di sini adalah angkanya, bukan halamannya terbuka. Rekap yang
 * tampil rapi tetapi salah menghitung lebih berbahaya daripada tidak ada rekap
 * sama sekali — orang memakainya untuk laporan ke fakultas.
 */
class FinanceReportTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->actingAs($this->admin('bendahara'), 'web');
    }

    /**
     * Uang masuk dihitung dari baris pembayaran, bukan dari status invoice.
     * Invoice yang ditandai lunas tanpa pembayaran apa pun tidak menambah
     * pemasukan — statusnya bisa keliru, uangnya tidak.
     */
    public function test_income_counts_payments_not_invoice_status(): void
    {
        $paid = $this->registration(500_000);
        $this->pay($paid, 500_000);

        $claimed = $this->registration(500_000);
        $claimed->update(['status' => 'paid']); // ditandai lunas, tanpa baris pembayaran

        $this->assertSame(500000.0, app(FinanceSummary::class)->received());
    }

    public function test_a_half_paid_instalment_splits_between_income_and_receivable(): void
    {
        $registration = $this->registration(350_000);
        $registration->update(['installment_plan' => true]);
        $this->pay($registration, 200_000);

        $summary = app(FinanceSummary::class);

        $this->assertSame(200000.0, $summary->received());
        $this->assertSame(150000.0, $summary->outstanding());
    }

    /**
     * Yang dibebaskan voucher bukan piutang: uangnya tidak akan pernah datang.
     * Mencampurnya membuat piutang terlihat lebih besar daripada kenyataannya.
     */
    public function test_waived_and_failed_invoices_are_not_receivables(): void
    {
        $this->registration(0)->update(['payment_method' => 'waived', 'discount_amount' => 500_000]);
        $this->registration(500_000)->update(['status' => 'failed']);
        $this->registration(300_000); // satu-satunya yang benar-benar ditunggu

        $summary = app(FinanceSummary::class);

        $this->assertSame(300000.0, $summary->outstanding());
        $this->assertSame(500000.0, $summary->waived());
    }

    public function test_income_is_split_by_how_it_arrived(): void
    {
        $viaGateway = $this->registration(500_000);
        $this->pay($viaGateway, 500_000, 'gateway');

        $byHand = $this->registration(200_000);
        $this->pay($byHand, 200_000, 'manual');

        $this->assertSame(
            ['gateway' => 500000.0, 'manual' => 200000.0],
            app(FinanceSummary::class)->receivedByMethod(),
        );
    }

    /** Edition lain tidak boleh ikut terhitung. */
    public function test_the_recap_stays_inside_the_active_edition(): void
    {
        $this->pay($this->registration(500_000), 500_000);

        $old = Edition::create(['name' => 'ICOMAN 2025', 'is_active' => false]);
        $this->pay($this->registration(900_000, $old), 900_000);

        $this->assertSame(500000.0, app(FinanceSummary::class)->received());
    }

    public function test_the_overdue_count_only_covers_instalments_that_already_started(): void
    {
        ImportantDate::create([
            'edition_id' => $this->edition->id,
            'kind' => 'installment',
            'label' => ['en' => 'Instalment settlement', 'id' => 'Pelunasan cicilan'],
            'date' => now()->subDay()->toDateString(),
        ]);

        $started = $this->registration(350_000);
        $started->update(['installment_plan' => true]);
        $this->pay($started, 200_000);

        // Memilih cicilan tapi belum membayar sepeser pun: belum menunggak.
        $notStarted = $this->registration(350_000);
        $notStarted->update(['installment_plan' => true]);

        $this->assertSame(1, app(FinanceSummary::class)->overdueInstallments());
    }

    /**
     * Daftar berjudul "Piutang" tidak boleh memuat invoice yang sudah lunas.
     * Sisanya disaring di SQL, sehingga daftar dan berkas exportnya berisi
     * hal yang sama persis.
     */
    public function test_a_settled_invoice_is_not_listed_as_a_receivable(): void
    {
        $settled = $this->registration(500_000);
        $this->pay($settled, 500_000);

        $owing = $this->registration(300_000);

        $ids = app(FinanceSummary::class)->stillOwing()->pluck('id')->all();

        $this->assertSame([$owing->id], $ids);

        Livewire::test(OutstandingInvoices::class)
            ->assertCanSeeTableRecords([$owing])
            ->assertCanNotSeeTableRecords([$settled]);
    }

    /**
     * Tarif peserta internasional ditetapkan dalam USD. Menampilkannya dengan
     * label IDR membuat angka 25 terbaca dua puluh lima rupiah.
     */
    public function test_a_foreign_currency_fee_is_not_labelled_as_rupiah(): void
    {
        RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'International participant'],
            'audience' => 'participant',
            'registrant_category' => 'international',
            'price_regular' => 25,
            'currency' => 'USD',
            'idr_exchange_rate' => 16_000,
        ]);

        Livewire::test(RevenueByCategory::class)
            ->assertSee('USD 25,00')
            ->assertDontSee('IDR 25');
    }

    public function test_the_page_and_its_three_parts_render(): void
    {
        $this->pay($this->registration(500_000), 500_000);

        Livewire::test(FinanceReport::class)->assertOk();
        Livewire::test(FinanceStats::class)->assertOk()->assertSee('Uang Masuk');
        Livewire::test(RevenueByCategory::class)->assertOk()->assertSee('Pemasukan per Kategori');
        Livewire::test(OutstandingInvoices::class)->assertOk()->assertSee('Piutang');
    }

    public function test_the_recap_is_closed_to_roles_that_do_not_handle_money(): void
    {
        foreach (['superadmin', 'admin_registrasi', 'bendahara'] as $role) {
            $this->actingAs($this->admin($role), 'web');
            $this->assertTrue(FinanceReport::canAccess(), $role.' seharusnya bisa membuka rekap.');
        }

        foreach (['content_admin', 'reviewer'] as $role) {
            $this->actingAs($this->admin($role), 'web');
            $this->assertFalse(FinanceReport::canAccess(), $role.' tidak punya urusan dengan rekap keuangan.');
        }
    }

    private function admin(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => 'secret-password',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function registration(int $amount, ?Edition $edition = null): Registration
    {
        $edition ??= $this->edition;

        $author = Author::create([
            'name' => 'Peserta',
            'email' => 'peserta-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => $amount,
            'installment_first_amount' => 200_000,
            'currency' => 'IDR',
        ]);

        return Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => $amount,
            'currency' => 'IDR',
            'payment_method' => 'gateway',
            'status' => 'pending',
        ]);
    }

    private function pay(Registration $registration, int $amount, string $method = 'gateway'): void
    {
        $registration->payments()->create([
            'method' => $method,
            'gateway_name' => $method === 'gateway' ? 'borderpay' : null,
            'amount' => $amount,
            'status' => 'success',
        ]);
    }
}
