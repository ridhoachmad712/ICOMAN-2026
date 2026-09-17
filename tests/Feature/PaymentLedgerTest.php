<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Buku transaksi di panel admin.
 *
 * Sebelum halaman ini ada, tabel `payments` tidak punya pintu sama sekali:
 * status invoice terlihat, tapi uang yang membentuknya tidak. Bendahara yang
 * harus mencocokkan dengan mutasi bank tidak punya apa pun untuk dicocokkan.
 */
class PaymentLedgerTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->actingAs($this->admin('admin_registrasi'), 'web');
    }

    public function test_the_ledger_shows_every_attempt_not_only_the_successful_ones(): void
    {
        $registration = $this->registration();

        $paid = $this->payment($registration, 'success', 'gateway');
        $failed = $this->payment($registration, 'failed', 'gateway');

        Livewire::test(ListPayments::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$paid, $failed]);
    }

    /**
     * Baris manual berarti ada orang yang memutuskan uangnya sudah diterima,
     * bukan gateway yang mengabarkannya. Itu yang paling perlu mudah dicari.
     */
    public function test_the_manual_tab_isolates_what_an_admin_recorded_by_hand(): void
    {
        $registration = $this->registration();

        $byHand = $this->payment($registration, 'success', 'manual');
        $byGateway = $this->payment($registration, 'success', 'gateway');

        Livewire::test(ListPayments::class)
            ->set('activeTab', 'manual')
            ->assertCanSeeTableRecords([$byHand])
            ->assertCanNotSeeTableRecords([$byGateway]);
    }

    /** Cicilan harus terbaca urutannya, bukan dua baris yang tampak kembar. */
    public function test_instalments_are_labelled_first_and_settlement(): void
    {
        $registration = $this->registration();
        $registration->update(['installment_plan' => true]);

        $first = $this->payment($registration, 'success', 'gateway', 200_000);
        $second = $this->payment($registration, 'success', 'gateway', 150_000);

        Livewire::test(ListPayments::class)
            ->set('activeTab', 'all')
            ->assertTableColumnStateSet('installment', 'Cicilan pertama', $first)
            ->assertTableColumnStateSet('installment', 'Pelunasan', $second);
    }

    public function test_a_single_payment_is_not_called_an_instalment(): void
    {
        $payment = $this->payment($this->registration(), 'success', 'gateway');

        Livewire::test(ListPayments::class)
            ->set('activeTab', 'all')
            ->assertTableColumnStateSet('installment', 'Pembayaran penuh', $payment);
    }

    /**
     * Catatan tidak disunting. Yang sudah terjadi adalah riwayat, dan
     * memperbaikinya dengan mengubah barisnya justru menghapus buktinya.
     */
    public function test_transactions_can_never_be_created_edited_or_deleted(): void
    {
        $payment = $this->payment($this->registration(), 'success', 'gateway');

        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($payment));
        $this->assertFalse(PaymentResource::canDelete($payment));
        $this->assertArrayNotHasKey('edit', PaymentResource::getPages());
    }

    public function test_only_the_committee_roles_reach_the_ledger(): void
    {
        foreach (['superadmin', 'admin_registrasi'] as $role) {
            $this->actingAs($this->admin($role), 'web');
            $this->assertTrue(PaymentResource::canAccess(), $role.' seharusnya bisa membuka buku transaksi.');
        }

        foreach (['content_admin', 'reviewer'] as $role) {
            $this->actingAs($this->admin($role), 'web');
            $this->assertFalse(PaymentResource::canAccess(), $role.' tidak punya urusan dengan transaksi.');
        }
    }

    /**
     * "Tandai Lunas" adalah jaring pengaman, bukan pembayaran baru. Kalau ia
     * mencatat total tagihan pada invoice yang sudah menerima cicilan pertama,
     * bukunya menunjukkan uang yang tidak pernah ada.
     */
    public function test_marking_paid_records_the_outstanding_amount_not_the_total(): void
    {
        $registration = $this->registration();
        $registration->update(['installment_plan' => true]);
        $this->payment($registration, 'success', 'gateway', 200_000);

        $this->actingAs($this->admin('superadmin'), 'web');

        Livewire::test(\App\Filament\Resources\Registrations\Pages\ListRegistrations::class)
            ->callAction(
                \Filament\Actions\Testing\TestAction::make('verify')->table($registration),
            );

        $registration->refresh();

        $this->assertSame('paid', $registration->status);
        $this->assertSame(350000.0, $registration->paidAmount(), 'Tercatat lebih banyak daripada tagihannya.');
        $this->assertSame(150000.0, (float) $registration->payments()->where('method', 'manual')->value('amount'));
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

    private function registration(): Registration
    {
        $author = Author::create([
            'name' => 'Peserta',
            'email' => 'peserta-'.uniqid().'@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
            'registrant_category' => 'general',
        ]);

        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Participant'],
            'audience' => 'participant',
            'registrant_category' => 'general',
            'price_regular' => 350_000,
            'currency' => 'IDR',
        ]);

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'amount' => 350_000,
            'currency' => 'IDR',
            'payment_method' => 'gateway',
            'status' => 'pending',
        ]);
    }

    private function payment(Registration $registration, string $status, string $method, int $amount = 350_000): Payment
    {
        return $registration->payments()->create([
            'method' => $method,
            'gateway_name' => $method === 'gateway' ? 'borderpay' : null,
            'gateway_reference' => $method === 'gateway' ? 'ICOMAN-'.$registration->id.'-'.uniqid() : null,
            'amount' => $amount,
            'status' => $status,
        ]);
    }
}
