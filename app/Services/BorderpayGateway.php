<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Lapisan tipis di atas HTTP API BorderPay.
 *
 * Dipisahkan dari BorderpayService supaya tes bisa menggantinya tanpa menyentuh
 * jaringan.
 */
class BorderpayGateway
{
    public const BASE_URL = 'https://borderpay.id/api/v1';

    /** Host yang sah untuk pay_url; selain ini ditolak sebelum author diarahkan. */
    public const CHECKOUT_HOST = 'borderpay.id';

    /**
     * Gangguan sesaat di sisi gateway tidak perlu sampai ke author.
     *
     * Mengulang pembuatan pembayaran aman karena `reference_id` yang sama
     * dikirim ulang: menurut dokumentasinya, permintaan berulang dengan
     * reference_id sama mengembalikan objek yang sama, bukan tagihan kedua.
     */
    private const ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 400;

    /**
     * Membuat permintaan pembayaran.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createPayment(string $apiKey, array $body): array
    {
        return $this->send(
            $this->client($apiKey)->post(self::BASE_URL.'/payments', $body)
        );
    }

    /**
     * Status sebuah pembayaran, ditanyakan langsung ke gateway.
     *
     * Inilah satu-satunya sumber kebenaran status di integrasi ini: webhook
     * BorderPay tidak ditandatangani, jadi isinya tidak pernah dipercaya.
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $apiKey, string $reference): array
    {
        return $this->send(
            $this->client($apiKey)->get(self::BASE_URL.'/payments/'.rawurlencode($reference))
        );
    }

    /** @return array<string, mixed> */
    public function cancelPayment(string $apiKey, string $reference): array
    {
        return $this->send(
            $this->client($apiKey)->post(self::BASE_URL.'/payments/'.rawurlencode($reference).'/cancel')
        );
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(30)
            ->retry(self::ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception) => self::isTransient($exception), throw: false);
    }

    /** Layak diulang: gangguan jaringan, atau kesalahan di sisi server gateway. */
    private static function isTransient(?\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response->serverError();
    }

    /** @return array<string, mixed> */
    private function send(Response $response): array
    {
        if ($response->failed()) {
            $error = $response->json('error') ?? [];

            throw new \RuntimeException(sprintf(
                'BorderPay menolak permintaan (%s): %s %s',
                $response->status(),
                $error['code'] ?? 'unknown_error',
                $error['message'] ?? '',
            ));
        }

        return (array) $response->json();
    }
}
