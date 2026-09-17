<?php

namespace Tests\Feature;

use App\Services\BorderpayGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perilaku lapisan HTTP ke BorderPay.
 */
class BorderpayGatewayTest extends TestCase
{
    /** @return array<string, mixed> */
    private function body(): array
    {
        return ['amount' => 350000, 'reference_id' => 'ICOMAN-1-X'];
    }

    public function test_a_transient_server_error_is_retried(): void
    {
        Http::fake([
            'borderpay.id/*' => Http::sequence()
                ->push(['error' => ['code' => 'internal', 'message' => 'oops']], 500)
                ->push(['reference_id' => 'ICOMAN-1-X', 'status' => 'pending', 'pay_url' => 'https://borderpay.id/pay/ICOMAN-1-X'], 201),
        ]);

        $result = app(BorderpayGateway::class)->createPayment('bp_test_key', $this->body());

        $this->assertSame('https://borderpay.id/pay/ICOMAN-1-X', $result['pay_url']);
        Http::assertSentCount(2);
    }

    /**
     * Pengulangan membawa reference_id yang sama. Inilah yang membuat mengulang
     * aman: BorderPay mengembalikan objek yang sama, bukan tagihan kedua.
     */
    public function test_every_attempt_carries_the_same_reference(): void
    {
        Http::fake([
            'borderpay.id/*' => Http::sequence()
                ->push(['error' => ['code' => 'internal', 'message' => 'oops']], 500)
                ->push(['reference_id' => 'ICOMAN-1-X', 'pay_url' => 'https://borderpay.id/pay/ICOMAN-1-X'], 201),
        ]);

        app(BorderpayGateway::class)->createPayment('bp_test_key', $this->body());

        Http::assertSent(fn ($request) => ($request->data()['reference_id'] ?? null) === 'ICOMAN-1-X');
    }

    /** Penolakan yang memang salah di pihak kita tidak diulang berkali-kali. */
    public function test_a_validation_refusal_is_not_retried(): void
    {
        Http::fake([
            'borderpay.id/*' => Http::response(['error' => ['code' => 'validation_failed', 'message' => 'amount invalid']], 422),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            app(BorderpayGateway::class)->createPayment('bp_test_key', $this->body());
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_the_api_key_travels_as_a_bearer_token(): void
    {
        Http::fake(['borderpay.id/*' => Http::response(['reference_id' => 'ICOMAN-1-X', 'status' => 'paid', 'amount' => 350000])]);

        app(BorderpayGateway::class)->getPayment('bp_test_key', 'ICOMAN-1-X');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer bp_test_key'));
    }

    /** Nomor order ikut di jalur URL, jadi harus lolos encoding dengan benar. */
    public function test_the_reference_is_encoded_into_the_path(): void
    {
        Http::fake(['borderpay.id/*' => Http::response(['reference_id' => 'ICOMAN-1-X', 'status' => 'paid', 'amount' => 1])]);

        app(BorderpayGateway::class)->getPayment('bp_test_key', 'ICOMAN-1-X');

        Http::assertSent(fn ($request) => $request->url() === 'https://borderpay.id/api/v1/payments/ICOMAN-1-X');
    }
}
