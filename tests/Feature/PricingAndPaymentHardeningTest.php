<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Submission;
use App\Services\ConferenceDeadlines;
use App\Services\KaseraGateway;
use App\Services\KaseraService;
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

        // Kasera Pay menagih IDR: 25 USD x 16.000 = 400.000.
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
        config()->set('services.kasera.api_key', 'kp_test_key');
        [$registration] = $this->payableRegistration();

        $calls = 0;
        $this->mockGateway(function () use (&$calls) {
            $calls++;

            return ['id' => 'payreq_abc', 'checkout_url' => 'https://pay.kasera.id/p/xK3f'];
        });

        $service = app(KaseraService::class);
        $first = $service->createCheckoutRedirect($registration);
        $second = $service->createCheckoutRedirect($registration->refresh());

        $this->assertSame($first, $second);
        // Tab kedua tidak boleh membuka permintaan pembayaran baru di Kasera.
        $this->assertSame(1, $calls);
        $this->assertSame(1, $registration->payments()->count());
    }

    /**
     * Referensi kita dikirim sebagai Idempotency-Key. Itulah satu-satunya yang
     * mencegah tagihan ganda kalau request pertama sempat timeout.
     */
    public function test_the_idempotency_key_is_our_own_reference(): void
    {
        config()->set('services.kasera.api_key', 'kp_test_key');
        [$registration] = $this->payableRegistration();

        $seen = null;
        $this->mockGateway(function (array $body, string $key) use (&$seen) {
            $seen = ['body' => $body, 'key' => $key];

            return ['id' => 'payreq_abc', 'checkout_url' => 'https://pay.kasera.id/p/xK3f'];
        });

        app(KaseraService::class)->createCheckoutRedirect($registration);
        $payment = $registration->payments()->firstOrFail();

        $this->assertSame($payment->gateway_reference, $seen['key']);
        $this->assertSame($payment->gateway_reference, $seen['body']['external_id']);
        $this->assertSame((int) $payment->amount, $seen['body']['amount']);
        // Tanpa object checkout, create diperlakukan sebagai Direct API.
        $this->assertArrayHasKey('checkout', $seen['body']);
        // Nomor dari Kasera-lah yang dipakai untuk menanyakan status.
        $this->assertSame('payreq_abc', $payment->gateway_payment_id);
    }

    public function test_a_checkout_url_from_an_unexpected_host_is_rejected(): void
    {
        config()->set('services.kasera.api_key', 'kp_test_key');
        [$registration] = $this->payableRegistration();

        $this->mockGateway(fn () => ['id' => 'payreq_abc', 'checkout_url' => 'https://evil.example.com/p/pay']);

        $this->expectException(\RuntimeException::class);
        app(KaseraService::class)->createCheckoutRedirect($registration);
    }

    public function test_a_repeated_webhook_is_recorded_once_and_keeps_the_invoice_paid(): void
    {
        [$registration, $payment] = $this->payableRegistration(withPayment: true);

        $service = app(KaseraService::class);
        $event = $this->paidEvent($payment);

        $service->applyEvent($event);
        $service->applyEvent($event); // pengiriman ulang dari Kasera

        $registration->refresh();
        $payment->refresh();

        $this->assertSame('paid', $registration->status);
        $this->assertSame('success', $payment->status);
        // Event dengan id yang sama hanya dicatat sekali.
        $this->assertCount(1, $payment->notification_history);
    }

    public function test_a_webhook_whose_amount_does_not_match_is_ignored(): void
    {
        [$registration, $payment] = $this->payableRegistration(withPayment: true);

        $event = $this->paidEvent($payment);
        $event['data']['amount'] = 1;

        $this->assertNull(app(KaseraService::class)->applyEvent($event));
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

    /** @return array{0: Registration, 1: Payment|null} */
    private function payableRegistration(bool $withPayment = false): array
    {
        $edition = $this->edition();
        $author = $this->author('participant', 'general');
        $this->fee($edition, 'participant', 'general', 50000);

        $registration = app(RegistrationProvisioner::class)->ensureFor($author);

        $payment = null;
        if ($withPayment) {
            $payment = $registration->payments()->create([
                'method' => 'gateway', 'gateway_name' => 'kasera',
                'gateway_reference' => 'ICOMAN-'.$registration->id.'-TEST',
                'gateway_payment_id' => 'payreq_'.$registration->id,
                'amount' => $registration->amount, 'status' => 'initiated',
            ]);
            $registration->update(['gateway_transaction_id' => $payment->gateway_reference]);
        }

        return [$registration, $payment];
    }

    private function mockGateway(callable $create): void
    {
        $this->app->bind(KaseraGateway::class, function () use ($create) {
            return new class($create) extends KaseraGateway
            {
                public function __construct(private $create) {}

                public function createTransaction(string $apiKey, array $body, string $idempotencyKey): array
                {
                    return ($this->create)($body, $idempotencyKey);
                }
            };
        });
    }

    /** @return array<string, mixed> */
    private function paidEvent(Payment $payment): array
    {
        return [
            'id' => 'evt_'.$payment->gateway_payment_id,
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => [
                'payment_request_id' => $payment->gateway_payment_id,
                'amount' => (int) $payment->amount,
                'currency' => 'IDR',
                'paid_at' => now()->toIso8601String(),
            ],
        ];
    }
}
