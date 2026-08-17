<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.midtrans.server_key'));
    }

    /**
     * Buat transaksi Snap untuk sebuah registrasi, catat percobaan ke `payments`,
     * dan kembalikan redirect URL Midtrans.
     */
    public function createSnapRedirect(Registration $registration): string
    {
        $orderId = $this->makeOrderId($registration);

        $payment = $registration->payments()->create([
            'method' => 'gateway',
            'gateway_name' => 'midtrans',
            'gateway_reference' => $orderId,
            'amount' => $registration->amount,
            'status' => 'initiated',
        ]);

        $author = $registration->author;

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) round((float) $registration->amount),
            ],
            'customer_details' => [
                'first_name' => $author?->name,
                'email' => $author?->email,
                'phone' => $author?->phone,
            ],
            'item_details' => [[
                'id' => 'reg-'.$registration->id,
                'price' => (int) round((float) $registration->amount),
                'quantity' => 1,
                'name' => \Illuminate\Support\Str::limit('Registration '.$registration->id, 45, ''),
            ]],
        ];

        $result = Snap::createTransaction($params);

        $registration->update(['gateway_transaction_id' => $orderId]);
        $payment->update(['raw_response' => (array) $result]);

        return $result->redirect_url;
    }

    /**
     * Verifikasi keaslian payload webhook Midtrans (WAJIB sebelum dipercaya).
     * signature_key = sha512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');

        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .$serverKey
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /**
     * Proses notifikasi yang SUDAH terverifikasi signature-nya.
     * Mengupdate status registrasi + mencatat hasil ke `payments`.
     */
    public function applyNotification(array $payload): ?Registration
    {
        $orderId = $payload['order_id'] ?? null;
        if (! $orderId) {
            return null;
        }

        $payment = Payment::where('gateway_reference', $orderId)->latest('id')->first();
        $registration = $payment?->registration;

        if (! $registration) {
            return null;
        }

        $status = $payload['transaction_status'] ?? '';
        $fraud = $payload['fraud_status'] ?? 'accept';

        [$regStatus, $payStatus] = match (true) {
            in_array($status, ['capture', 'settlement'], true) && $fraud === 'accept' => ['paid', 'success'],
            $status === 'pending' => ['pending', 'initiated'],
            in_array($status, ['deny', 'cancel', 'expire'], true) => ['failed', 'failed'],
            default => [$registration->status, 'initiated'],
        };

        $payment?->update(['status' => $payStatus, 'raw_response' => $payload]);

        $registration->update([
            'status' => $regStatus,
            'gateway_payload' => $payload,
            'paid_at' => $regStatus === 'paid' ? now() : $registration->paid_at,
        ]);

        return $registration;
    }

    private function makeOrderId(Registration $registration): string
    {
        $attempt = $registration->payments()->count() + 1;

        return sprintf('ICOMAN-REG-%d-%d', $registration->id, $attempt);
    }
}
