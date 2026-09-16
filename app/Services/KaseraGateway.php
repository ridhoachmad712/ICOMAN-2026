<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Lapisan tipis di atas HTTP API Kasera Pay.
 *
 * Dipisahkan dari KaseraService supaya tes bisa menggantinya tanpa menyentuh
 * jaringan — sama seperti MidtransGateway sebelumnya.
 */
class KaseraGateway
{
    public const BASE_URL = 'https://pay.kasera.id/v1';

    /** Host yang sah untuk checkout_url; selain ini ditolak sebelum author diarahkan. */
    public const CHECKOUT_HOST = 'pay.kasera.id';

    /**
     * Gangguan sesaat di sisi gateway tidak perlu sampai ke author.
     *
     * Mengulang create aman karena Idempotency-Key-nya sama: menurut
     * dokumentasinya, pengiriman ulang dengan body identik mengembalikan objek
     * yang sama, bukan membuat tagihan kedua.
     */
    private const ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 400;

    /**
     * Membuat permintaan pembayaran.
     *
     * Idempotency-Key adalah satu-satunya yang mencegah tagihan ganda di sisi
     * Kasera: pengiriman ulang dengan body identik mengembalikan objek yang
     * sama, bukan membuat yang baru.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createTransaction(string $apiKey, array $body, string $idempotencyKey): array
    {
        return $this->send(
            Http::withToken($apiKey)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->acceptJson()
                ->timeout(30)
                ->retry(self::ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception, $request) => self::isTransient($exception), throw: false)
                ->post(self::BASE_URL.'/transactions', $body)
        );
    }

    /** @return array<string, mixed> */
    public function retrieveTransaction(string $apiKey, string $id): array
    {
        return $this->send(
            Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->retry(self::ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception, $request) => self::isTransient($exception), throw: false)
                ->get(self::BASE_URL.'/transactions/'.$id)
        );
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

            // request_id dicatat supaya kegagalan di sisi Kasera bisa ditanyakan
            // ke dukungan mereka dengan menyebut permintaan yang tepat.
            throw new \RuntimeException(sprintf(
                'Kasera Pay menolak permintaan (%s): %s %s [request_id: %s]',
                $response->status(),
                $error['code'] ?? 'unknown_error',
                $error['message'] ?? '',
                $error['request_id'] ?? $response->header('X-Request-Id') ?: 'tidak ada',
            ));
        }

        return (array) $response->json();
    }
}
