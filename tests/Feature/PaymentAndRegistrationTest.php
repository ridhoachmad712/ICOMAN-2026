<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Services\KaseraService;
use App\Services\RegistrationProvisioner;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentAndRegistrationTest extends TestCase
{
    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-test');

        $body = json_encode(['id' => 'evt_1', 'type' => 'payment.paid', 'data' => ['payment_request_id' => 'payreq_fake', 'amount' => 750000]]);

        $this->postSigned($body, 't='.now()->timestamp.',v1=deadbeef')->assertForbidden();
    }

    /** Signature yang benar atas raw body harus diterima dan menandai lunas. */
    public function test_a_signed_webhook_marks_the_invoice_paid(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-test');
        [$registration, $payment] = $this->gatewayPayment();

        $body = json_encode($this->event($payment));

        $this->postSigned($body, $this->signature($body, 'whsec-test'))->assertOk();

        $this->assertSame('paid', $registration->refresh()->status);
    }

    /**
     * Signature dihitung atas raw body. Kalau kode kita meng-encode ulang hasil
     * parse, urutan kunci bisa berubah dan kiriman yang sah ikut ditolak.
     */
    public function test_the_signature_is_checked_against_the_raw_body(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-test');
        [$registration, $payment] = $this->gatewayPayment();

        // Spasi dan urutan kunci sengaja tidak kanonik.
        $body = '{ "data" : {"amount":750000,"payment_request_id":"'.$payment->gateway_payment_id.'"},  "type":"payment.paid", "id":"evt_raw" }';

        $this->postSigned($body, $this->signature($body, 'whsec-test'))->assertOk();

        $this->assertSame('paid', $registration->refresh()->status);
    }

    /** Kiriman lama yang disadap tidak boleh bisa diputar ulang. */
    public function test_a_stale_timestamp_is_refused(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-test');
        [$registration, $payment] = $this->gatewayPayment();

        $body = json_encode($this->event($payment));
        $stale = (string) now()->subMinutes(10)->timestamp;

        $this->postSigned($body, $this->signature($body, 'whsec-test', $stale))->assertForbidden();

        $this->assertSame('pending', $registration->refresh()->status);
    }

    /** Selama rotasi secret, header membawa dua v1 - satu yang cocok sudah cukup. */
    public function test_either_signature_of_a_rotating_secret_is_accepted(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-new');
        [$registration, $payment] = $this->gatewayPayment();

        $body = json_encode($this->event($payment));
        $timestamp = (string) now()->timestamp;
        $header = $this->signature($body, 'whsec-old', $timestamp)
            .',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec-new');

        $this->postSigned($body, $header)->assertOk();

        $this->assertSame('paid', $registration->refresh()->status);
    }

    /** Kiriman uji dari dashboard tidak membawa pembayaran, tapi tetap harus dijawab 2xx. */
    public function test_a_test_ping_is_acknowledged(): void
    {
        config()->set('services.kasera.webhook_secret', 'whsec-test');

        $body = json_encode(['id' => 'evt_ping', 'type' => 'test.ping', 'livemode' => false, 'test' => true]);

        $this->postSigned($body, $this->signature($body, 'whsec-test'))->assertOk();
    }

    /**
     * Webhook datang dari server Kasera tanpa CSRF token, jadi route-nya harus
     * ada di daftar pengecualian. CSRF tidak aktif saat tes berjalan, sehingga
     * tes HTTP di atas tetap hijau walau pengecualiannya salah alamat — dan
     * itu persis yang sempat terjadi saat route-nya dipindah dari Midtrans.
     */
    public function test_the_webhook_route_is_exempt_from_csrf(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertContains(
            ltrim(parse_url(route('payment.kasera.notification'), PHP_URL_PATH), '/'),
            $excluded,
        );
    }

    public function test_registration_uses_one_fixed_price(): void
    {
        $fee = new RegistrationFee(['price_regular' => 750_000]);
        $this->assertSame('750000.00', $fee->currentPrice());
        $this->assertFalse(Schema::hasColumn('registration_fees', 'price_early_bird'));
    }

    public function test_auto_invoice_ignores_fees_from_an_inactive_edition(): void
    {
        $active = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $inactive = Edition::create(['name' => 'ICOMAN 2025', 'is_active' => false]);
        $author = Author::create([
            'name' => 'Author',
            'email' => 'author@example.test',
            'password' => 'secret-password',
            'participation_type' => 'participant',
        ]);
        RegistrationFee::create([
            'edition_id' => $inactive->id,
            'category' => ['en' => 'Presenter'],
            'audience' => 'participant',
            'price_regular' => 750_000,
            'currency' => 'IDR',
        ]);

        // Tarif hanya ada di edisi non-aktif → tidak ada invoice yang dibuat.
        $this->assertNull(app(RegistrationProvisioner::class)->ensureFor($author));
        $this->assertDatabaseCount('registrations', 0);
        $this->assertTrue($active->is_active);
    }

    public function test_valid_webhook_marks_payment_paid_and_a_later_failure_cannot_downgrade_it(): void
    {
        [$registration, $payment] = $this->gatewayPayment();
        $service = app(KaseraService::class);

        $service->applyEvent($this->event($payment));
        $this->assertSame('paid', $registration->refresh()->status);
        $this->assertNotNull($registration->paid_at);

        // Status gagal hanya bisa datang dari GET; yang sudah lunas tidak boleh turun.
        $service->applyTransaction($this->transaction($payment, 'expired'));
        $this->assertSame('paid', $registration->refresh()->status);
        $this->assertNotNull($registration->paid_at);
    }

    public function test_webhook_rejects_amount_mismatch(): void
    {
        [$registration, $payment] = $this->gatewayPayment();

        $event = $this->event($payment);
        $event['data']['amount'] = 1;

        $result = app(KaseraService::class)->applyEvent($event);

        $this->assertNull($result);
        $this->assertSame('pending', $registration->refresh()->status);
    }

    /** Kasera tidak mengirim webhook untuk kegagalan; statusnya datang dari GET. */
    public function test_a_retrieved_expiry_fails_the_invoice(): void
    {
        [$registration, $payment] = $this->gatewayPayment();

        app(KaseraService::class)->applyTransaction($this->transaction($payment, 'expired'));

        $this->assertSame('failed', $registration->refresh()->status);
        $this->assertSame('failed', $payment->refresh()->status);
    }

    /** Pengiriman bersifat at-least-once: event dengan id sama tidak dicatat dua kali. */
    public function test_a_repeated_event_is_recorded_once(): void
    {
        [$registration, $payment] = $this->gatewayPayment();
        $service = app(KaseraService::class);

        $service->applyEvent($this->event($payment));
        $service->applyEvent($this->event($payment));

        $this->assertCount(1, $payment->refresh()->notification_history);
    }

    private function gatewayPayment(): array
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $author = Author::create([
            'name' => 'Author',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret-password',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Presenter'],
            'price_regular' => 750_000,
            'currency' => 'IDR',
        ]);
        $registration = Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'payment_method' => 'gateway',
            'amount' => 750_000,
            'status' => 'pending',
            'gateway_transaction_id' => 'ICOMAN-'.$edition->id.'-TEST',
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'method' => 'gateway',
            'gateway_name' => 'kasera',
            'gateway_reference' => $registration->gateway_transaction_id,
            'gateway_payment_id' => 'payreq_'.uniqid(),
            'amount' => 750_000,
            'status' => 'initiated',
        ]);

        return [$registration, $payment];
    }

    private function postSigned(string $body, string $signature): TestResponse
    {
        return $this->call('POST', route('payment.kasera.notification'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_KASERA_SIGNATURE_V1' => $signature,
        ], $body);
    }

    private function signature(string $body, string $secret, ?string $timestamp = null): string
    {
        $timestamp ??= (string) now()->timestamp;

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** @return array<string, mixed> */
    private function event(Payment $payment): array
    {
        return [
            'id' => 'evt_'.$payment->gateway_payment_id,
            'type' => 'payment.paid',
            'livemode' => false,
            'data' => [
                'payment_request_id' => $payment->gateway_payment_id,
                'amount' => 750000,
                'currency' => 'IDR',
                'paid_at' => now()->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function transaction(Payment $payment, string $status): array
    {
        return [
            'id' => $payment->gateway_payment_id,
            'status' => $status,
            'amount' => 750000,
            'currency' => 'IDR',
        ];
    }
}
