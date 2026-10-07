<?php

namespace Tests\Feature;

use App\Filament\Author\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\VoucherRedeemer;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVoucherTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        app(SiteSettings::class)->fill(['sinta3_fee' => 300_000])->save();
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function invoice(string $email, string $audience = 'presenter', bool $sinta = false): Registration
    {
        $author = Author::create([
            'name' => 'Voucher User', 'email' => $email, 'password' => 'secret-password',
            'participation_type' => $audience, 'registrant_category' => 'general',
        ]);
        $fee = RegistrationFee::firstOrCreate(
            ['edition_id' => $this->edition->id, 'audience' => $audience, 'registrant_category' => 'general'],
            ['category' => ['id' => 'Registrasi', 'en' => 'Registration'], 'price_regular' => 400_000, 'currency' => 'IDR'],
        );
        $submission = $audience === 'presenter' ? Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'title' => 'Voucher Paper', 'abstract' => 'Abstract', 'status' => 'accepted',
            'sinta3_offered' => true, 'journal_target' => $sinta ? 'sinta3' : 'regular',
        ]) : null;
        $quote = $fee->quote();
        $quote['journal_target'] = $sinta ? 'sinta3' : 'regular';
        $quote['addon_amount'] = $sinta ? $quote['quoted_addon_amount'] : 0;

        return Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'registration_fee_id' => $fee->id, 'submission_id' => $submission?->id,
            'payment_method' => 'gateway', 'amount' => $quote['base_amount'] + $quote['addon_amount'],
            'pricing_snapshot' => $quote, 'status' => 'pending',
        ]);
    }

    private function voucher(array $attributes = []): Voucher
    {
        return Voucher::create(array_merge([
            'edition_id' => $this->edition->id, 'code' => 'PROMO-2026',
            'host_name' => 'Promo konferensi', 'type' => 'discount',
            'audience' => 'all', 'discount_kind' => 'fixed', 'discount_value' => 100_000,
            'quota' => 2, 'is_active' => true,
        ], $attributes));
    }

    public function test_superadmin_can_create_a_discount_code_with_a_custom_user_limit(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'voucher-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        Livewire::test(CreateVoucher::class)
            ->fillForm([
                'host_name' => 'Diskon Peserta', 'code' => 'promo-peserta',
                'type' => 'discount', 'audience' => 'participant',
                'discount_kind' => 'percent', 'discount_value' => 25,
                'edition_id' => $this->edition->id, 'quota' => 3, 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = Voucher::where('code', 'PROMO-PESERTA')->firstOrFail();
        $this->assertSame('discount', $voucher->type);
        $this->assertSame('participant', $voucher->audience);
        $this->assertSame(3, $voucher->quota);
    }

    public function test_superadmin_can_create_a_free_paper_code(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Admin', 'email' => 'free-paper-admin@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        Livewire::test(CreateVoucher::class)
            ->fillForm([
                'host_name' => 'Beasiswa Paper', 'code' => 'gratis-paper',
                'type' => 'free_paper', 'edition_id' => $this->edition->id,
                'quota' => 5, 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $voucher = Voucher::where('code', 'GRATIS-PAPER')->firstOrFail();
        $this->assertSame('free_paper', $voucher->type);
        $this->assertSame('presenter', $voucher->audience);
        $this->assertSame(5, $voucher->quota);
    }

    public function test_fixed_discount_reduces_the_invoice_and_quota_counts_distinct_users(): void
    {
        $voucher = $this->voucher(['quota' => 1]);
        $first = $this->invoice('first-voucher@example.test');
        app(VoucherRedeemer::class)->redeem($first, 'promo-2026');

        $this->assertSame('300000.00', $first->refresh()->amount);
        $this->assertSame('100000.00', $first->discount_amount);
        $this->assertSame('pending', $first->status);
        $this->assertSame(0, $voucher->remainingSlots());

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->redeem($this->invoice('second-voucher@example.test'), 'PROMO-2026');
    }

    public function test_percentage_discount_works_for_seminar_participants_and_appears_on_the_invoice(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('author'));
        $this->voucher(['discount_kind' => 'percent', 'discount_value' => 25, 'audience' => 'participant']);
        $registration = $this->invoice('seminar-voucher@example.test', 'participant');
        $this->actingAs($registration->author, 'author');

        Livewire::test(ViewRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertSee('voucher_code', escape: false);

        $this->post(route('author.registration.voucher', $registration), ['voucher_code' => 'PROMO-2026'])
            ->assertRedirect();

        $this->assertSame('300000.00', $registration->refresh()->amount);
        $this->assertSame('100000.00', $registration->discount_amount);
    }

    public function test_free_paper_waives_the_base_fee_but_keeps_a_journal_addon(): void
    {
        $this->voucher(['type' => 'free_paper', 'audience' => 'presenter', 'discount_kind' => null, 'discount_value' => null]);
        $registration = $this->invoice('free-paper@example.test', 'presenter', true);

        app(VoucherRedeemer::class)->redeem($registration, 'PROMO-2026');

        $this->assertSame('300000.00', $registration->refresh()->amount);
        $this->assertSame('400000.00', $registration->discount_amount);
        $this->assertSame('pending', $registration->status);
    }

    public function test_discount_is_retained_when_the_journal_choice_changes(): void
    {
        $this->voucher();
        $registration = $this->invoice('journal-voucher@example.test');
        app(VoucherRedeemer::class)->redeem($registration, 'PROMO-2026');

        $this->actingAs($registration->author, 'author')
            ->patch(route('author.registration.journal', $registration), ['journal_target' => 'sinta3'])
            ->assertRedirect();

        $this->assertSame('600000.00', $registration->refresh()->amount);
        $this->assertSame('100000.00', $registration->discount_amount);
    }

    public function test_free_paper_cannot_be_used_for_a_seminar_only_registration(): void
    {
        $this->voucher(['type' => 'free_paper', 'audience' => 'presenter']);

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->redeem($this->invoice('seminar-only@example.test', 'participant'), 'PROMO-2026');
    }

    public function test_a_discount_slot_cannot_be_released_after_a_partial_payment(): void
    {
        $this->voucher();
        $registration = $this->invoice('part-paid-voucher@example.test');
        app(VoucherRedeemer::class)->redeem($registration, 'PROMO-2026');
        Payment::create([
            'registration_id' => $registration->id,
            'method' => 'gateway', 'amount' => 100_000, 'status' => 'success',
        ]);

        $this->expectException(ValidationException::class);
        app(VoucherRedeemer::class)->release(VoucherRedemption::where('registration_id', $registration->id)->firstOrFail());
    }
}
