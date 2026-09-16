<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherRedeemer;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Voucher co-host: satu kode per institusi mitra dengan kuota paper gratis.
 * Membebaskan biaya registrasi dasar presenter; add-on Jurnal SINTA 3 tetap
 * ditagih lewat Kasera Pay.
 */
class CohostVoucherTest extends TestCase
{
    private Edition $edition;

    private RegistrationFee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        // Add-on SINTA 3 harus bernilai agar bisa dibuktikan tetap ditagih.
        app(SiteSettings::class)->fill(['sinta3_fee' => 300_000])->save();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $this->fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Presenter', 'id' => 'Presenter'],
            'audience' => 'presenter',
            'registrant_category' => 'general',
            'price_regular' => 400_000,
            'currency' => 'IDR',
        ]);
    }

    private function voucher(array $attributes = []): Voucher
    {
        return Voucher::create(array_merge([
            'edition_id' => $this->edition->id,
            'code' => 'COHOST-UNM',
            'host_name' => 'Universitas Mitra',
            'quota' => 4,
            'is_active' => true,
        ], $attributes));
    }

    private function invoice(string $email, string $journalTarget = 'regular'): Registration
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $submission = Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'title' => 'Judul', 'abstract' => 'Isi.', 'status' => 'accepted',
            'loa_issued_at' => now(), 'sinta3_offered' => true, 'journal_target' => $journalTarget,
        ]);

        $quote = $this->fee->quote();
        $quote['journal_target'] = $journalTarget;
        $quote['addon_amount'] = $journalTarget === 'sinta3' ? $quote['quoted_addon_amount'] : 0;

        return Registration::create([
            'edition_id' => $this->edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $this->fee->id,
            'submission_id' => $submission->id,
            'payment_method' => 'gateway',
            'amount' => $quote['base_amount'] + $quote['addon_amount'],
            'pricing_snapshot' => $quote,
            'status' => 'pending',
        ]);
    }

    private function redeem(Registration $registration, string $code = 'COHOST-UNM'): void
    {
        app(VoucherRedeemer::class)->redeem($registration, $code);
    }

    public function test_a_voucher_waives_the_base_fee_and_settles_the_invoice(): void
    {
        $this->voucher();
        $registration = $this->invoice('satu@example.test');
        $this->assertSame('400000.00', $registration->amount);

        $this->redeem($registration);
        $registration->refresh();

        $this->assertSame('0.00', $registration->amount);
        $this->assertSame('400000.00', $registration->discount_amount);
        $this->assertSame('paid', $registration->status);
        // Bukan transaksi Kasera Pay Rp 0 — laporan keuangan harus bisa membedakannya.
        $this->assertSame('waived', $registration->payment_method);
        $this->assertTrue($registration->isWaived());
        $this->assertNotNull($registration->paid_at);
        $this->assertNull($registration->gateway_transaction_id);
    }

    /** SINTA 3 tetap ditagih: voucher hanya menanggung biaya dasar. */
    public function test_the_sinta3_add_on_stays_payable(): void
    {
        $this->voucher();
        $registration = $this->invoice('sinta@example.test', 'sinta3');
        $addon = (int) $registration->priceDetails()['addon_amount'];
        $this->assertGreaterThan(0, $addon);

        $this->redeem($registration);
        $registration->refresh();

        $this->assertSame(number_format($addon, 2, '.', ''), $registration->amount);
        $this->assertSame('pending', $registration->status);
        $this->assertSame('gateway', $registration->payment_method);
    }

    public function test_the_quota_is_consumed_and_then_refuses_further_use(): void
    {
        $voucher = $this->voucher(['quota' => 2]);

        $this->redeem($this->invoice('a@example.test'));
        $this->redeem($this->invoice('b@example.test'));

        $this->assertSame(0, $voucher->refresh()->remainingSlots());

        $this->expectException(ValidationException::class);
        $this->redeem($this->invoice('c@example.test'));
    }

    public function test_the_code_is_matched_regardless_of_case_and_spacing(): void
    {
        $this->voucher();
        $registration = $this->invoice('kapital@example.test');

        $this->redeem($registration, '  cohost-unm  ');

        $this->assertSame('paid', $registration->refresh()->status);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->voucher();

        $this->expectException(ValidationException::class);
        $this->redeem($this->invoice('salah@example.test'), 'BUKAN-KODE');
    }

    /** Kode edisi lain tidak boleh menyeberang. */
    public function test_a_voucher_from_another_edition_is_rejected(): void
    {
        $other = Edition::create(['name' => 'ICOMAN 2027', 'is_active' => false]);
        $this->voucher(['edition_id' => $other->id]);

        $this->expectException(ValidationException::class);
        $this->redeem($this->invoice('edisi@example.test'));
    }

    public function test_a_deactivated_voucher_is_rejected(): void
    {
        $this->voucher(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->redeem($this->invoice('nonaktif@example.test'));
    }

    public function test_an_expired_voucher_is_rejected(): void
    {
        $this->voucher(['expires_at' => now()->subDay()]);

        $this->expectException(ValidationException::class);
        $this->redeem($this->invoice('kedaluwarsa@example.test'));
    }

    public function test_one_invoice_cannot_use_two_vouchers(): void
    {
        $this->voucher();
        $this->voucher(['code' => 'COHOST-LAIN']);
        $registration = $this->invoice('dobel@example.test', 'sinta3');

        $this->redeem($registration);

        $this->expectException(ValidationException::class);
        $this->redeem($registration->refresh(), 'COHOST-LAIN');
    }

    /** Peserta seminar (tanpa paper) tidak termasuk cakupan voucher. */
    public function test_a_seminar_only_registration_is_rejected(): void
    {
        $this->voucher();
        $registration = $this->invoice('peserta@example.test');
        $registration->forceFill(['submission_id' => null])->save();

        $this->expectException(ValidationException::class);
        $this->redeem($registration->refresh());
    }

    public function test_a_voucher_cannot_be_applied_while_a_payment_is_running(): void
    {
        $this->voucher();
        $registration = $this->invoice('berjalan@example.test');
        Payment::create([
            'registration_id' => $registration->id,
            'method' => 'gateway',
            'amount' => $registration->amount,
            'status' => 'initiated',
        ]);

        $this->expectException(ValidationException::class);
        $this->redeem($registration->refresh());
    }

    public function test_a_voucher_cannot_be_applied_to_a_settled_invoice(): void
    {
        $this->voucher();
        $registration = $this->invoice('lunas@example.test');
        $registration->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $this->redeem($registration->refresh());
    }

    /** Admin melepas slot telantar: kuota kembali, invoice kembali penuh. */
    public function test_releasing_a_slot_restores_the_quota_and_the_invoice(): void
    {
        $voucher = $this->voucher(['quota' => 1]);
        $registration = $this->invoice('telantar@example.test');
        $this->redeem($registration);

        $this->assertSame(0, $voucher->refresh()->remainingSlots());

        app(VoucherRedeemer::class)->release($registration->refresh()->redemption);

        $registration->refresh();
        $this->assertSame(1, $voucher->refresh()->remainingSlots());
        $this->assertSame('400000.00', $registration->amount);
        $this->assertSame('0.00', $registration->discount_amount);
        $this->assertSame('pending', $registration->status);
        $this->assertSame('gateway', $registration->payment_method);
        $this->assertNull($registration->voucher_id);
        $this->assertNull($registration->paid_at);
    }

    /** Slot yang add-on-nya sudah dibayar lewat Kasera Pay tidak boleh dilepas sembarangan. */
    public function test_a_slot_with_real_money_paid_cannot_be_released(): void
    {
        $this->voucher();
        $registration = $this->invoice('bayar@example.test', 'sinta3');
        $this->redeem($registration);
        $registration->refresh()->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->release($registration->refresh()->redemption);
    }

    public function test_the_author_can_redeem_through_the_invoice_page(): void
    {
        $this->voucher();
        $registration = $this->invoice('portal@example.test');

        $this->actingAs($registration->author, 'author')
            ->post(route('author.registration.voucher', $registration), ['voucher_code' => 'cohost-unm'])
            ->assertRedirect();

        $this->assertSame('paid', $registration->refresh()->status);
    }

    public function test_someone_else_cannot_redeem_against_your_invoice(): void
    {
        $this->voucher();
        $registration = $this->invoice('pemilik@example.test');
        $intruder = Author::create([
            'name' => 'Orang Lain', 'email' => 'lain@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);

        $this->actingAs($intruder, 'author')
            ->post(route('author.registration.voucher', $registration), ['voucher_code' => 'COHOST-UNM'])
            ->assertForbidden();

        $this->assertSame('pending', $registration->refresh()->status);
    }

    private function admin(string $role, string $email): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate($role, 'web');
        $user = User::create(['name' => 'Petugas', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole($role);
        $this->actingAs($user, 'web');

        return $user;
    }

    public function test_the_admin_list_renders_with_slot_usage(): void
    {
        $this->admin('superadmin', 'super-voucher@example.test');
        $voucher = $this->voucher();
        $this->redeem($this->invoice('pakai@example.test'));

        Livewire::test(ListVouchers::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$voucher])
            ->assertSee('COHOST-UNM');
    }

    /** Admin registrasi boleh melihat kuota, tapi tidak menentukan siapa dapat gratis. */
    public function test_registration_admins_can_read_but_not_change_vouchers(): void
    {
        $this->admin('admin_registrasi', 'reg-voucher@example.test');

        $this->assertTrue(VoucherResource::canAccess());
        $this->assertFalse(VoucherResource::canCreate());
        $this->assertFalse(VoucherResource::canEdit($this->voucher()));
    }

    public function test_reviewers_cannot_reach_vouchers(): void
    {
        $this->admin('reviewer', 'reviewer-voucher@example.test');

        $this->assertFalse(VoucherResource::canAccess());
    }

    /** Kode yang sudah dipakai adalah catatan siapa dapat jatah — jangan bisa dihapus. */
    public function test_a_used_voucher_cannot_be_deleted(): void
    {
        $this->admin('superadmin', 'super-hapus@example.test');
        $voucher = $this->voucher();

        $this->assertTrue(VoucherResource::canDelete($voucher));

        $this->redeem($this->invoice('terpakai@example.test'));

        $this->assertFalse(VoucherResource::canDelete($voucher->refresh()));
    }

    public function test_the_invoice_page_offers_the_voucher_box_then_shows_the_discount(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        $this->voucher();
        $registration = $this->invoice('halaman@example.test');
        $this->actingAs($registration->author, 'author');

        // Paper yang ditawari SINTA 3 menempuh langkah pilihan jurnal lebih
        // dulu; kotak voucher ada di halaman tagihan sesudahnya.
        if ($registration->submission?->sinta3_offered) {
            $this->patch(route('author.registration.journal', $registration), ['journal_target' => 'regular']);
        }

        Livewire::test(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertOk()
            // Nama field, bukan labelnya: halaman ini dua bahasa.
            ->assertSee('voucher_code', escape: false);

        $this->redeem($registration);

        // Setelah dipakai: kotaknya hilang, potongannya terlihat di rincian biaya.
        Livewire::test(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertOk()
            ->assertDontSee('voucher_code', escape: false)
            ->assertSee('COHOST-UNM');
    }
}
