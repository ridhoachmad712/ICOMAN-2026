<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationFee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Webhook BorderPay tidak ditandatangani, dan integrasi ini memperlakukannya
 * begitu.
 *
 * Verifikasi yang didokumentasikan hanya mencocokkan token statis di header
 * `x-borderpay-token`: tidak ada HMAC, tidak ada timestamp (diperiksa terhadap
 * berkas OpenAPI resminya, nol kemunculan untuk signature/hmac/sha256/
 * timestamp). Token semacam itu mengotentikasi pemanggil, bukan isi kiriman,
 * jadi siapa pun yang memperolehnya bisa mengarang "payment.paid".
 *
 * Karena itu isi payload tidak pernah dipakai selain `reference_id`, dan
 * statusnya ditanyakan ulang ke gateway. Tes di bawah inilah yang menjaga
 * keputusan itu tetap berlaku.
 */
class BorderpayWebhookTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.borderpay.api_key', 'bp_test_key');
        config()->set('services.borderpay.webhook_token', 'bpt_rahasia');
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    /** @return array{0: Registration, 1: Payment} */
    private function order(int $amount = 750_000): array
    {
        $author = Author::create([
            'name' => 'Penulis', 'email' => uniqid().'@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $fee = RegistrationFee::create([
            'edition_id' => $this->edition->id,
            'category' => ['en' => 'Presenter'],
            'audience' => 'presenter', 'registrant_category' => 'general',
            'price_regular' => $amount, 'currency' => 'IDR',
        ]);
        $registration = Registration::create([
            'edition_id' => $this->edition->id, 'author_id' => $author->id,
            'registration_fee_id' => $fee->id, 'payment_method' => 'gateway',
            'amount' => $amount, 'pricing_snapshot' => $fee->quote(), 'status' => 'pending',
            'gateway_transaction_id' => 'ICOMAN-ORDER-1',
        ]);
        $payment = $registration->payments()->create([
            'method' => 'gateway', 'gateway_name' => 'borderpay',
            'gateway_reference' => 'ICOMAN-ORDER-1',
            'amount' => $amount, 'status' => 'initiated',
        ]);

        return [$registration, $payment];
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'event' => 'payment.paid',
            'mode' => 'test',
            'data' => [
                'reference_id' => 'ICOMAN-ORDER-1',
                'order_id' => 'ICOMAN-ORDER-1',
                'status' => 'paid',
                'amount' => 750_000,
                'method' => 'qris',
            ],
        ], $overrides);
    }

    private function deliver(array $payload, ?string $token = 'bpt_rahasia'): TestResponse
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BORDERPAY_EVENT' => $payload['event'] ?? 'payment.paid'];

        if ($token !== null) {
            $headers['HTTP_X_BORDERPAY_TOKEN'] = $token;
        }

        return $this->call('POST', route('payment.borderpay.notification'), [], [], [], $headers, json_encode($payload));
    }

    /** @param  array<string, mixed>  $remote */
    private function fakeStatus(array $remote): void
    {
        Http::fake(['borderpay.id/*' => Http::response($remote)]);
    }

    // --- Pintu masuk --------------------------------------------------------

    public function test_a_wrong_token_is_refused(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 750_000]);

        $this->deliver($this->payload(), token: 'bpt_salah')->assertUnauthorized();

        $this->assertSame('pending', $registration->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_missing_token_is_refused(): void
    {
        $this->order();

        $this->deliver($this->payload(), token: null)->assertUnauthorized();
    }

    /** Tanpa token tersimpan, tidak ada kiriman yang boleh lolos. */
    public function test_nothing_passes_when_no_token_is_configured(): void
    {
        config()->set('services.borderpay.webhook_token', '');
        $this->order();

        $this->deliver($this->payload(), token: 'apa pun')->assertUnauthorized();
    }

    // --- Isi kiriman tidak dipercaya ----------------------------------------

    /**
     * Inti seluruh rancangan ini: kiriman bertoken benar yang mengaku "paid"
     * TIDAK melunasi apa pun bila gateway mengatakan masih pending.
     */
    public function test_a_payload_claiming_paid_is_ignored_when_the_gateway_says_pending(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'pending', 'amount' => 750_000]);

        $this->deliver($this->payload())->assertOk();

        $registration->refresh();
        $this->assertSame('pending', $registration->status);
        $this->assertNull($registration->paid_at);
    }

    /** Dan sebaliknya: gateway bilang lunas, maka lunas. */
    public function test_the_gateway_answer_settles_the_invoice(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 750_000, 'paid_at' => now()->toIso8601String()]);

        $this->deliver($this->payload())->assertOk();

        $registration->refresh();
        $this->assertSame('paid', $registration->status);
        $this->assertNotNull($registration->paid_at);
    }

    /**
     * Nominal di payload juga tidak dipakai. Kiriman yang mengaku nominal lain
     * tetap menghasilkan pelunasan sesuai angka yang dijawab gateway.
     */
    public function test_the_amount_in_the_payload_is_ignored(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 750_000]);

        $this->deliver($this->payload(['data' => ['amount' => 1]]))->assertOk();

        $this->assertSame('paid', $registration->refresh()->status);
    }

    /** Nominal dari gateway yang tidak cocok dengan order kita ditolak. */
    public function test_a_gateway_amount_that_does_not_match_the_order_is_refused(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 1]);

        $this->deliver($this->payload())->assertNotFound();

        $this->assertSame('pending', $registration->refresh()->status);
    }

    /**
     * Order yang tidak kita kenali tidak pernah ditanyakan ke gateway. Tanpa
     * penjagaan ini, kiriman palsu bisa dipakai memancing panggilan keluar
     * sebanyak-banyaknya.
     */
    public function test_an_unknown_reference_never_reaches_the_gateway(): void
    {
        $this->order();
        $this->fakeStatus(['reference_id' => 'PALSU', 'status' => 'paid', 'amount' => 750_000]);

        $this->deliver($this->payload(['data' => ['reference_id' => 'PALSU']]))->assertNotFound();

        Http::assertNothingSent();
    }

    // --- Kegagalan dan kedaluwarsa ------------------------------------------

    /**
     * BorderPay mengabarkan kegagalan dan kedaluwarsa, yang tidak dilakukan
     * gateway sebelumnya. Keduanya menutup ordernya tanpa perlu ada yang
     * menekan tombol Periksa Status.
     */
    public function test_an_expiry_fails_the_invoice(): void
    {
        [$registration, $payment] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'expired', 'amount' => 750_000]);

        $this->deliver($this->payload(['event' => 'payment.expired', 'data' => ['status' => 'expired']]))->assertOk();

        $this->assertSame('failed', $registration->refresh()->status);
        $this->assertSame('failed', $payment->refresh()->status);
    }

    public function test_a_failure_fails_the_invoice(): void
    {
        [$registration] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'failed', 'amount' => 750_000]);

        $this->deliver($this->payload(['event' => 'payment.failed', 'data' => ['status' => 'failed']]))->assertOk();

        $this->assertSame('failed', $registration->refresh()->status);
    }

    /**
     * Dokumentasi BorderPay: bila sempat expired lalu paid, yang final adalah
     * paid. Yang sudah lunas tidak boleh diturunkan kiriman yang datang belakangan.
     */
    public function test_a_late_expiry_cannot_undo_a_settled_invoice(): void
    {
        [$registration] = $this->order();

        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 750_000]);
        $this->deliver($this->payload())->assertOk();
        $this->assertSame('paid', $registration->refresh()->status);

        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'expired', 'amount' => 750_000]);
        $this->deliver($this->payload(['event' => 'payment.expired', 'data' => ['status' => 'expired']]))->assertOk();

        $registration->refresh();
        $this->assertSame('paid', $registration->status);
        $this->assertNotNull($registration->paid_at);
    }

    /** Kiriman berulang tidak dicatat dua kali. */
    public function test_a_repeated_delivery_is_recorded_once(): void
    {
        [$registration, $payment] = $this->order();
        $this->fakeStatus(['reference_id' => 'ICOMAN-ORDER-1', 'status' => 'paid', 'amount' => 750_000]);

        $this->deliver($this->payload())->assertOk();
        $this->deliver($this->payload())->assertOk();

        $this->assertCount(1, $payment->refresh()->notification_history);
        $this->assertSame('paid', $registration->refresh()->status);
    }

    // --- Rute ---------------------------------------------------------------

    /**
     * Webhook datang tanpa CSRF token. CSRF mati saat tes berjalan, jadi tes
     * HTTP mana pun tetap hijau walau pengecualiannya salah alamat; daftarnya
     * dibaca langsung.
     */
    public function test_the_webhook_route_is_exempt_from_csrf(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertContains(
            ltrim(parse_url(route('payment.borderpay.notification'), PHP_URL_PATH), '/'),
            $excluded,
        );
    }
}
