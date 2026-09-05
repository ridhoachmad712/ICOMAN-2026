<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Services\ConferenceDeadlines;
use App\Services\MidtransGateway;
use App\Services\MidtransService;
use App\Services\RegistrationProvisioner;
use App\Settings\SiteSettings;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Menjaga aturan yang menyentuh UANG: harga dibekukan per invoice, konversi USD,
 * penutupan tenggat, serta pembayaran yang tidak boleh dobel/diproses dua kali.
 */
class PricingAndPaymentHardeningTest extends TestCase
{
    // ---------- Harga & kurs ----------

    public function test_idr_fee_quotes_at_face_value(): void
    {
        $quote = $this->fee($this->edition(), 'participant', 'general', 50000)->quote();

        $this->assertSame(50000, $quote['base_amount']);
        $this->assertSame('IDR', $quote['currency']);
        $this->assertSame(1, (int) $quote['exchange_rate']);
    }

    public function test_usd_fee_is_billed_in_idr_using_the_committee_rate(): void
    {
        $fee = $this->fee($this->edition(), 'participant', 'international', 25, 'USD', 16000);

        $quote = $fee->quote();

        // Midtrans menagih IDR: 25 USD x 16.000 = 400.000.
        $this->assertSame(400000, $quote['base_amount']);
        $this->assertSame('IDR', $quote['currency']);
        $this->assertSame('USD', $quote['source_currency']);
    }

    public function test_usd_fee_refuses_to_quote_before_the_rate_is_set(): void
    {
        $fee = $this->fee($this->edition(), 'participant', 'international', 25, 'USD', null);

        $this->expectException(ValidationException::class);
        $fee->quote();
    }

    public function test_international_checkout_is_blocked_until_the_rate_exists(): void
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'international');
        $this->fee($edition, 'participant', 'international', 25, 'USD', null);

        // Lebih baik menolak membuat invoice daripada menagih nominal yang salah.
        $this->expectException(ValidationException::class);
        app(RegistrationProvisioner::class)->ensureFor($author);
    }

    // ---------- Snapshot harga ----------

    public function test_a_later_fee_change_does_not_alter_an_issued_invoice(): void
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'general');
        $fee = $this->fee($edition, 'participant', 'general', 50000);

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);
        $this->assertSame(50000.0, (float) $registration->amount);

        // Panitia menaikkan tarif setelah invoice terbit.
        $fee->update(['price_regular' => 90000]);

        $this->assertSame(50000.0, (float) $registration->refresh()->amount);
        $this->assertSame(50000, $registration->priceDetails()['base_amount']);
    }

    public function test_sinta3_addon_uses_the_amount_quoted_when_the_invoice_was_issued(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter', 'general');
        $submission = $this->acceptedSubmission($edition, $author);
        $this->fee($edition, 'presenter', 'general', 400000);

        $this->setSinta3Fee(300000);
        $registration = app(RegistrationProvisioner::class)->ensureFor($author);

        // Biaya add-on dinaikkan SETELAH invoice terbit.
        $this->setSinta3Fee(500000);

        $this->actingAs($author, 'author')
            ->patch(route('author.registration.journal', $registration), ['journal_target' => 'sinta3'])
            ->assertRedirect();

        // Author membayar sesuai yang ditawarkan padanya, bukan tarif baru.
        $this->assertSame(700000.0, (float) $registration->refresh()->amount);
        $this->assertSame('sinta3', $submission->refresh()->journal_target);
    }

    public function test_journal_choice_is_locked_while_a_payment_is_in_flight(): void
    {
        $edition = $this->edition();
        $author = $this->author('presenter', 'general');
        $this->acceptedSubmission($edition, $author);
        $this->fee($edition, 'presenter', 'general', 400000);
        $this->setSinta3Fee(300000);

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);
        $registration->payments()->create([
            'method' => 'gateway', 'gateway_name' => 'midtrans',
            'gateway_reference' => 'ICOMAN-'.$registration->id.'-INFLIGHT',
            'amount' => $registration->amount, 'status' => 'initiated',
        ]);

        // Mengubah nominal saat order gateway sudah berjalan akan membuat
        // tagihan dan transaksi tidak cocok.
        $this->actingAs($author, 'author')
            ->patch(route('author.registration.journal', $registration), ['journal_target' => 'sinta3'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(400000.0, (float) $registration->refresh()->amount);
    }

    // ---------- Tenggat ----------

    public function test_payment_is_refused_after_its_deadline_has_passed(): void
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'general');
        $this->fee($edition, 'participant', 'general', 50000);

        ImportantDate::create([
            'edition_id' => $edition->id,
            'label' => ['en' => 'Registration payment deadline'],
            'kind' => 'payment',
            'closes_at' => now()->subDay(),
        ]);

        $this->assertFalse(app(ConferenceDeadlines::class)->isOpen('payment', $edition->id));

        $this->expectException(ValidationException::class);
        app(RegistrationProvisioner::class)->ensureFor($author);
    }

    public function test_a_stage_without_a_configured_deadline_stays_open(): void
    {
        $edition = $this->edition();

        $this->assertTrue(app(ConferenceDeadlines::class)->isOpen('payment', $edition->id));
        $this->assertTrue(app(ConferenceDeadlines::class)->isOpen('full_paper', $edition->id));
    }

    // ---------- Pembayaran: dobel & idempotensi ----------

    public function test_a_second_checkout_reuses_the_existing_order_instead_of_creating_another(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');
        [$registration] = $this->payableRegistration();

        $calls = 0;
        $this->mockGateway(function () use (&$calls) {
            $calls++;

            return (object) ['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/abc'];
        });

        $service = app(MidtransService::class);
        $first = $service->createSnapRedirect($registration);
        $second = $service->createSnapRedirect($registration->refresh());

        $this->assertSame($first, $second);
        // Tab kedua tidak boleh membuka order baru di Midtrans.
        $this->assertSame(1, $calls);
        $this->assertSame(1, $registration->payments()->count());
    }

    public function test_a_checkout_url_from_an_unexpected_host_is_rejected(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');
        [$registration] = $this->payableRegistration();

        $this->mockGateway(fn () => (object) ['redirect_url' => 'https://evil.example.com/snap/pay']);

        $this->expectException(\RuntimeException::class);
        app(MidtransService::class)->createSnapRedirect($registration);
    }

    public function test_a_repeated_webhook_is_recorded_once_and_keeps_the_invoice_paid(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');
        [$registration, $payment] = $this->payableRegistration(withPayment: true);

        $service = app(MidtransService::class);
        $payload = $this->settlementPayload($payment);

        $service->applyNotification($payload);
        $service->applyNotification($payload); // pengiriman ulang dari Midtrans

        $registration->refresh();
        $payment->refresh();

        $this->assertSame('paid', $registration->status);
        $this->assertSame('success', $payment->status);
        // Payload identik hanya dicatat sekali.
        $this->assertCount(1, $payment->notification_history);
    }

    public function test_a_webhook_whose_amount_does_not_match_is_ignored(): void
    {
        config()->set('services.midtrans.server_key', 'server-key');
        [$registration, $payment] = $this->payableRegistration(withPayment: true);

        $payload = $this->settlementPayload($payment);
        $payload['gross_amount'] = '1.00';

        $this->assertNull(app(MidtransService::class)->applyNotification($payload));
        $this->assertNotSame('paid', $registration->refresh()->status);
    }

    // ---------- Helper ----------

    private function edition(): Edition
    {
        return Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function author(string $type, string $category = 'general'): Author
    {
        return Author::create([
            'name' => 'Portal User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
            'participation_type' => $type,
            'registrant_category' => $category,
        ]);
    }

    private function fee(Edition $edition, string $audience, string $category = 'general', int $price = 500000, string $currency = 'IDR', ?int $rate = null): RegistrationFee
    {
        return RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => ucfirst($audience).' '.$category],
            'audience' => $audience,
            'registrant_category' => $category,
            'price_regular' => $price,
            'currency' => $currency,
            'idr_exchange_rate' => $rate,
        ]);
    }

    private function acceptedSubmission(Edition $edition, Author $author): Submission
    {
        $submission = Submission::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'title' => 'A paper',
            'abstract' => str_repeat('word ', 200),
            'status' => 'accepted',
        ]);
        $submission->forceFill(['loa_issued_at' => now(), 'sinta3_offered' => true])->save();

        return $submission;
    }

    private function setSinta3Fee(int $amount): void
    {
        $settings = app(SiteSettings::class);
        $settings->sinta3_fee = $amount;
        $settings->save();
        app()->forgetInstance(SiteSettings::class);
    }

    /** @return array{0: Registration, 1: \App\Models\Payment|null} */
    private function payableRegistration(bool $withPayment = false): array
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'general');
        $this->fee($edition, 'participant', 'general', 50000);

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);

        $payment = null;
        if ($withPayment) {
            $payment = $registration->payments()->create([
                'method' => 'gateway', 'gateway_name' => 'midtrans',
                'gateway_reference' => 'ICOMAN-'.$registration->id.'-TEST',
                'amount' => $registration->amount, 'status' => 'initiated',
            ]);
            $registration->update(['gateway_transaction_id' => $payment->gateway_reference]);
        }

        return [$registration, $payment];
    }

    private function mockGateway(callable $create): void
    {
        $this->app->bind(MidtransGateway::class, function () use ($create) {
            return new class($create) extends MidtransGateway
            {
                public function __construct(private $create) {}

                public function create(array $parameters): object
                {
                    return ($this->create)($parameters);
                }
            };
        });
    }

    private function settlementPayload(\App\Models\Payment $payment): array
    {
        return [
            'order_id' => $payment->gateway_reference,
            'status_code' => '200',
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ];
    }
}
