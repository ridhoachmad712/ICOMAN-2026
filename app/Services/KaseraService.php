<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Integrasi pembayaran Kasera Pay (menggantikan Midtrans).
 *
 * Dua perbedaan pokok dari gateway sebelumnya, dan keduanya membentuk kode di
 * bawah ini:
 *
 * 1. Nomor transaksi diterbitkan Kasera (`payreq_...`), bukan kita. Referensi
 *    kita sendiri dikirim sebagai `external_id` — yang oleh dokumentasinya
 *    disebut label dan tidak pernah men-dedupe — sekaligus sebagai
 *    Idempotency-Key, yang memang satu-satunya penjaga dari tagihan ganda.
 * 2. Satu-satunya webhook adalah `payment.paid`. Gagal dan kedaluwarsa tidak
 *    pernah dikirim, jadi hanya diketahui lewat synchronize().
 */
class KaseraService
{
    /** Kiriman webhook ditolak bila timestamp-nya melenceng lebih dari ini. */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    private string $apiKey;

    private string $webhookSecret;

    public function __construct(private KaseraGateway $gateway)
    {
        $key = rescue(fn () => siteSettings()->kasera_api_key, null, false);
        $this->apiKey = filled($key) ? $key : (string) config('services.kasera.api_key');

        $secret = rescue(fn () => siteSettings()->kasera_webhook_secret, null, false);
        $this->webhookSecret = filled($secret) ? $secret : (string) config('services.kasera.webhook_secret');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /** Test mode dikenali dari prefix key-nya, bukan dari saklar tersendiri. */
    public function isLiveMode(): bool
    {
        return str_starts_with($this->apiKey, 'kp_live_');
    }

    public function createCheckoutRedirect(Registration $registration): string
    {
        app(ConferenceDeadlines::class)->assertOpen('payment', $registration->edition_id);

        // Order dikunci sebelum request keluar; tab kedua memakai ulang baris ini.
        [$payment, $isNew] = DB::transaction(function () use ($registration): array {
            $registration = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($registration->status, ['pending', 'failed'], true), 403);
            abort_unless($registration->priceDetails()['currency'] === 'IDR' && (float) $registration->amount > 0, 422);

            $existing = $registration->payments()->where('method', 'gateway')->where('status', 'initiated')->latest('id')->first();
            if ($existing) {
                return [$existing, false];
            }

            abort_if($registration->payments()->where('status', 'success')->exists(), 409);

            $payment = $registration->payments()->create([
                'method' => 'gateway',
                'gateway_name' => 'kasera',
                'gateway_reference' => 'ICOMAN-'.$registration->id.'-'.Str::ulid(),
                'amount' => $registration->amount,
                'status' => 'initiated',
            ]);
            $registration->update(['gateway_transaction_id' => $payment->gateway_reference, 'status' => 'pending']);

            return [$payment, true];
        });

        if ($payment->checkout_url) {
            return $payment->checkout_url;
        }

        if (! $isNew) {
            throw ValidationException::withMessages(['payment' => app()->getLocale() === 'id'
                ? 'Pembayaran sedang disiapkan atau menunggu rekonsiliasi. Gunakan Periksa Status; hubungi panitia jika tetap belum tersedia.'
                : 'Checkout is being prepared or awaiting reconciliation. Use Check Status; contact the committee if it remains unavailable.']);
        }

        $transaction = $this->gateway->createTransaction(
            $this->apiKey,
            $this->checkoutBody($registration, $payment),
            $payment->gateway_reference,
        );

        $url = (string) ($transaction['checkout_url'] ?? '');
        if (! str_starts_with($url, 'https://') || parse_url($url, PHP_URL_HOST) !== KaseraGateway::CHECKOUT_HOST) {
            throw new \RuntimeException('Invalid gateway checkout URL.');
        }

        $reference = (string) ($transaction['id'] ?? '');
        if ($reference === '') {
            throw new \RuntimeException('Gateway did not return a payment request id.');
        }

        $payment->update([
            'gateway_payment_id' => $reference,
            'checkout_url' => $url,
            'raw_response' => $transaction,
        ]);

        return $url;
    }

    /**
     * Isi permintaan untuk Kasera Pay Checkout.
     *
     * Object `checkout` wajib dikirim walau kosong: tanpanya create dianggap
     * Direct API dan metode yang butuh data pembeli ditolak 422. Nama, email,
     * dan telepon diminta di halamannya karena Virtual Account, kartu, dan
     * e-wallet masing-masing mensyaratkan salah satunya.
     *
     * @return array<string, mixed>
     */
    private function checkoutBody(Registration $registration, Payment $payment): array
    {
        return [
            'amount' => (int) $payment->amount,
            'description' => Str::limit('Registrasi '.(siteSettings()->conference_name ?: 'ICOMAN 2026').' #'.$registration->id, 255, ''),
            'external_id' => $payment->gateway_reference,
            'merchant_ref' => 'REG-'.$registration->id,
            'return_url' => route('payment.kasera.finish'),
            'checkout' => [
                'steps' => ['customer', 'payment_method', 'payment'],
                'is_name_required' => true,
                'is_email_required' => true,
                'is_phone_required' => true,
            ],
        ];
    }

    /**
     * Menyelaraskan status dari Kasera.
     *
     * Ini satu-satunya jalan mengetahui pembayaran yang gagal atau kedaluwarsa:
     * webhook-nya hanya mengabarkan yang berhasil.
     */
    public function synchronize(Registration $registration): void
    {
        $payments = $registration->payments()
            ->where('method', 'gateway')
            ->where('status', 'initiated')
            ->whereNotNull('gateway_payment_id')
            ->get();

        foreach ($payments as $payment) {
            $transaction = $this->gateway->retrieveTransaction($this->apiKey, $payment->gateway_payment_id);

            if (($transaction['id'] ?? null) !== $payment->gateway_payment_id) {
                throw new \RuntimeException('Gateway status did not identify the expected payment request.');
            }

            $this->applyTransaction($transaction);
        }
    }

    /**
     * Memverifikasi header Kasera-Signature-V1.
     *
     * Formatnya `t=<unix>,v1=<hex>` dengan v1 = HMAC-SHA256 atas
     * `<unix>.<raw body>`. Selama rotasi secret ada dua entri v1, jadi satu
     * yang cocok sudah cukup. Timestamp diperiksa supaya kiriman yang disadap
     * tidak bisa diputar ulang belakangan — inilah alasan header lama
     * (body-only) tidak dipakai meski masih dikirim.
     */
    public function verifySignature(string $rawBody, ?string $header): bool
    {
        if ($this->webhookSecret === '' || blank($header)) {
            return false;
        }

        $timestamp = null;
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $candidates[] = substr($part, 3);
            }
        }

        if (! is_numeric($timestamp) || $candidates === []) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);

        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menerapkan event webhook `payment.paid`.
     *
     * @param  array<string, mixed>  $event
     */
    public function applyEvent(array $event): ?Registration
    {
        if (($event['type'] ?? null) !== 'payment.paid') {
            return null;
        }

        $data = $event['data'] ?? [];
        $reference = $data['payment_request_id'] ?? null;
        $amount = $data['amount'] ?? null;

        if (! is_string($reference) || ! is_numeric($amount)) {
            return null;
        }

        return $this->settle($reference, 'succeeded', $amount, $event, is_string($event['id'] ?? null) ? $event['id'] : null);
    }

    /**
     * Menerapkan objek transaksi hasil GET.
     *
     * @param  array<string, mixed>  $transaction
     */
    public function applyTransaction(array $transaction): ?Registration
    {
        $reference = $transaction['id'] ?? null;
        $amount = $transaction['amount'] ?? null;
        $status = $transaction['status'] ?? null;

        if (! is_string($reference) || ! is_numeric($amount) || ! is_string($status)) {
            return null;
        }

        return $this->settle($reference, $status, $amount, $transaction, null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function settle(string $reference, string $status, int|string|float $amount, array $payload, ?string $eventId): ?Registration
    {
        $candidate = Payment::where('gateway_payment_id', $reference)
            ->where('gateway_name', 'kasera')
            ->where('method', 'gateway')
            ->first();

        if (! $candidate) {
            return null;
        }

        return DB::transaction(function () use ($candidate, $status, $amount, $payload, $eventId): ?Registration {
            $registration = Registration::whereKey($candidate->registration_id)->lockForUpdate()->first();
            $payment = Payment::whereKey($candidate->id)->lockForUpdate()->first();

            if (! $registration || ! $payment) {
                return null;
            }

            // Nominal yang tidak sama dengan order yang kita buat bukan pembayaran ini.
            if (number_format((float) $payment->amount, 2, '.', '') !== number_format((float) $amount, 2, '.', '')) {
                return null;
            }

            $success = $status === 'succeeded';
            $failure = in_array($status, ['failed', 'expired', 'canceled'], true);

            // Pengiriman bersifat at-least-once; id event yang sama tidak dicatat dua kali.
            $history = $payment->notification_history ?? [];
            $fingerprint = $eventId ?? hash('sha256', json_encode($payload));
            if (! collect($history)->contains('fingerprint', $fingerprint)) {
                $history[] = ['received_at' => now()->toIso8601String(), 'fingerprint' => $fingerprint, 'payload' => $payload];
            }
            $payment->notification_history = $history;

            if ($payment->status !== 'success') {
                $payment->status = $success ? 'success' : ($failure ? 'failed' : $payment->status);
                $payment->raw_response = $payload;
            }
            $payment->save();

            if ($registration->status !== 'paid') {
                if ($success) {
                    $matchesInvoice = (float) $payment->amount === (float) $registration->amount;
                    $registration->update([
                        'status' => $matchesInvoice ? 'paid' : 'pending_verification',
                        'paid_at' => $matchesInvoice ? now() : null,
                        'gateway_payload' => $payload,
                    ]);

                    if (! $matchesInvoice) {
                        Log::warning('Payment received against a different invoice amount; reconcile manually.', [
                            'registration_id' => $registration->id,
                            'payment_id' => $payment->id,
                        ]);
                    }
                } elseif ($failure && $registration->gateway_transaction_id === $payment->gateway_reference && $registration->status !== 'pending_verification') {
                    $registration->update(['status' => 'failed', 'gateway_payload' => $payload]);
                }
            }

            return $registration->refresh();
        });
    }
}
