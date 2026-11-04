<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Models\Voucher;
use App\Services\BorderpayService;
use App\Services\RegistrationProvisioner;
use App\Services\VoucherRedeemer;
use App\Settings\SiteSettings;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Pembayaran peserta seminar diatur terpisah dari presenter dan co-host. */
class ParticipantPaymentTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        foreach (['presenter', 'participant'] as $audience) {
            RegistrationFee::create([
                'edition_id' => $this->edition->id, 'category' => ['en' => ucfirst($audience)],
                'audience' => $audience, 'registrant_category' => 'general',
                'price_regular' => 400_000, 'currency' => 'IDR',
            ]);
        }
    }

    public function test_closing_presenter_payments_leaves_participants_able_to_pay(): void
    {
        app(SiteSettings::class)->fill(['payment_closed' => true])->save();

        $invoice = $this->provisioner()->ensureFor($this->account('participant'));
        $this->assertTrue($invoice->acceptsPayment());

        $this->assertRefused(fn () => $this->provisioner()->ensureFor($this->presenterWithLoa()));
        $this->assertSame(1, Registration::count());
    }

    public function test_a_participant_voucher_still_works_while_presenter_payments_are_closed(): void
    {
        app(SiteSettings::class)->fill(['payment_closed' => true])->save();
        Voucher::create([
            'edition_id' => $this->edition->id, 'code' => 'PESERTA', 'host_name' => 'Peserta', 'type' => 'discount',
            'audience' => 'participant', 'discount_kind' => 'fixed', 'discount_value' => 100_000,
            'quota' => 5, 'is_active' => true,
        ]);
        $invoice = $this->provisioner()->ensureFor($this->account('participant'));

        app(VoucherRedeemer::class)->redeem($invoice, 'PESERTA');

        $this->assertNotNull($invoice->refresh()->voucher_id);
    }

    public function test_the_participant_switch_closes_only_participants(): void
    {
        $invoice = $this->provisioner()->ensureFor($this->account('participant'));
        $presenterInvoice = $this->provisioner()->ensureFor($this->presenterWithLoa());

        app(SiteSettings::class)->fill(['participant_payment_closed' => true])->save();

        $this->assertFalse($invoice->acceptsPayment());
        $this->assertTrue($presenterInvoice->acceptsPayment());
        $this->assertRefused(fn () => app(BorderpayService::class)->createCheckoutRedirect($invoice));
        $this->assertRefused(fn () => $this->provisioner()->ensureFor($this->account('participant')));
    }

    public function test_the_participant_deadline_overrides_the_general_payment_deadline(): void
    {
        $this->deadline('payment', now()->subDay());
        $invoice = $this->provisioner()->ensureFor($this->account('participant'), byCommittee: true);
        $this->assertFalse($invoice->acceptsPayment(), 'Tanpa tenggat sendiri, ikut tenggat pembayaran umum.');

        $own = $this->deadline('participant_payment', now()->addWeek());
        $this->assertTrue($invoice->acceptsPayment());

        $own->update(['date' => now()->subHour(), 'closes_at' => now()->subHour()]);
        $this->assertFalse($invoice->acceptsPayment());
    }

    private function provisioner(): RegistrationProvisioner
    {
        return app(RegistrationProvisioner::class);
    }

    private function assertRefused(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Seharusnya ditolak karena pembayaran ditutup.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function deadline(string $kind, \DateTimeInterface $at): ImportantDate
    {
        return ImportantDate::create([
            'edition_id' => $this->edition->id, 'label' => ['en' => $kind], 'kind' => $kind,
            'date' => $at, 'closes_at' => $at, 'order' => 1,
        ]);
    }

    private function account(string $type): Author
    {
        return Author::create([
            'name' => 'Akun '.uniqid(), 'email' => fake()->unique()->safeEmail(), 'password' => 'secret-password',
            'participation_type' => $type, 'registrant_category' => 'general',
        ]);
    }

    private function presenterWithLoa(): Author
    {
        return Submission::create([
            'edition_id' => $this->edition->id, 'author_id' => $this->account('presenter')->id, 'title' => 'Paper',
            'abstract' => 'Abstract', 'keywords' => ['k'], 'status' => 'accepted', 'loa_issued_at' => now(),
        ])->author;
    }
}
