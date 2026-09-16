<?php

namespace Tests\Feature;

use App\Services\KaseraGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perilaku lapisan HTTP ke Kasera Pay.
 *
 * Ditulis setelah produksi mencatat `Kasera Pay menolak permintaan (500):
 * internal something went wrong`. Kode `internal` adalah kegagalan di sisi
 * mereka — bukan permintaan kita yang salah bentuk — jadi yang bisa kita
 * lakukan adalah mengulangnya, dan menyimpan request_id bila tetap gagal.
 */
class KaseraGatewayTest extends TestCase
{
    /** @return array<string, mixed> */
    private function body(): array
    {
        return ['amount' => 350000, 'description' => 'Registrasi', 'external_id' => 'ICOMAN-1-X'];
    }

    public function test_a_transient_server_error_is_retried(): void
    {
        Http::fake([
            'pay.kasera.id/*' => Http::sequence()
                ->push(['error' => ['code' => 'internal', 'message' => 'something went wrong']], 500)
                ->push(['id' => 'payreq_ok', 'checkout_url' => 'https://pay.kasera.id/p/ok'], 201),
        ]);

        $result = app(KaseraGateway::class)->createTransaction('kp_test_key', $this->body(), 'ICOMAN-1-X');

        $this->assertSame('payreq_ok', $result['id']);
        Http::assertSentCount(2);
    }

    /**
     * Pengulangan memakai Idempotency-Key yang sama. Inilah yang membuat
     * mengulang aman: Kasera mengembalikan objek yang sama, bukan tagihan kedua.
     */
    public function test_every_attempt_carries_the_same_idempotency_key(): void
    {
        Http::fake([
            'pay.kasera.id/*' => Http::sequence()
                ->push(['error' => ['code' => 'internal', 'message' => 'oops']], 500)
                ->push(['id' => 'payreq_ok', 'checkout_url' => 'https://pay.kasera.id/p/ok'], 201),
        ]);

        app(KaseraGateway::class)->createTransaction('kp_test_key', $this->body(), 'ICOMAN-1-X');

        Http::assertSent(fn (Request $request) => $request->header('Idempotency-Key') === ['ICOMAN-1-X']);
    }

    /** Penolakan yang memang salah di pihak kita tidak diulang berkali-kali. */
    public function test_a_validation_refusal_is_not_retried(): void
    {
        Http::fake([
            'pay.kasera.id/*' => Http::response(['error' => ['code' => 'validation_failed', 'message' => 'amount too small']], 422),
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            app(KaseraGateway::class)->createTransaction('kp_test_key', $this->body(), 'ICOMAN-1-X');
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** Kegagalan yang bertahan membawa request_id, supaya bisa ditanyakan ke Kasera. */
    public function test_a_persistent_failure_reports_the_request_id(): void
    {
        Http::fake([
            'pay.kasera.id/*' => Http::response(
                ['error' => ['code' => 'internal', 'message' => 'something went wrong', 'request_id' => 'req_abc123']],
                500,
            ),
        ]);

        try {
            app(KaseraGateway::class)->createTransaction('kp_test_key', $this->body(), 'ICOMAN-1-X');
            $this->fail('Seharusnya melempar exception.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('internal', $exception->getMessage());
            $this->assertStringContainsString('req_abc123', $exception->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_a_successful_first_attempt_is_sent_once(): void
    {
        Http::fake([
            'pay.kasera.id/*' => Http::response(['id' => 'payreq_ok', 'checkout_url' => 'https://pay.kasera.id/p/ok'], 201),
        ]);

        app(KaseraGateway::class)->createTransaction('kp_test_key', $this->body(), 'ICOMAN-1-X');

        Http::assertSentCount(1);
    }
}
