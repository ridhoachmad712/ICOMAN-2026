<?php

namespace App\Services;

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
                ->get(self::BASE_URL.'/transactions/'.$id)
        );
    }

    /** @return array<string, mixed> */
    private function send(Response $response): array
    {
        if ($response->failed()) {
            $error = $response->json('error') ?? [];

            throw new \RuntimeException(sprintf(
                'Kasera Pay menolak permintaan (%s): %s %s',
                $response->status(),
                $error['code'] ?? 'unknown_error',
                $error['message'] ?? '',
            ));
        }

        return (array) $response->json();
    }
}
