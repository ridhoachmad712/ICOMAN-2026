<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                'name' => Str::limit('Registration '.$registration->id, 45, ''),
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

        if ($serverKey === '' || blank($payload['signature_key'] ?? null)) {
            return false;
        }

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

        return DB::transaction(function () use ($orderId, $payload): ?Registration {
            $payment = Payment::where('gateway_reference', $orderId)->lockForUpdate()->latest('id')->first();
            $registration = $payment?->registration()->lockForUpdate()->first();

            if (! $payment || ! $registration) {
                return null;
            }

            $expectedAmount = number_format((float) $payment->amount, 2, '.', '');
            $receivedAmount = number_format((float) ($payload['gross_amount'] ?? -1), 2, '.', '');
            if (! hash_equals($expectedAmount, $receivedAmount)
                || $registration->gateway_transaction_id !== $orderId
                || $payment->method !== 'gateway') {
                Log::warning('Midtrans webhook: transaction mismatch', ['order_id' => $orderId]);

                return null;
            }

            $status = $payload['transaction_status'] ?? '';
            $fraud = $payload['fraud_status'] ?? 'accept';
            $isSuccess = in_array($status, ['capture', 'settlement'], true) && $fraud === 'accept';

            $payStatus = match (true) {
                $isSuccess => 'success',
                in_array($status, ['deny', 'cancel', 'expire'], true) => 'failed',
                default => 'initiated',
            };

            $payment->update(['status' => $payStatus, 'raw_response' => $payload]);

            // Status paid bersifat terminal: webhook lama/terlambat tidak boleh menurunkannya.
            if ($registration->status !== 'paid') {
                $regStatus = match (true) {
                    $isSuccess => 'paid',
                    in_array($status, ['deny', 'cancel', 'expire'], true) => 'failed',
                    default => $registration->status,
                };

                $registration->update([
                    'status' => $regStatus,
                    'gateway_payload' => $payload,
                    'paid_at' => $regStatus === 'paid' ? now() : null,
                ]);
            }

            return $registration->refresh();
        });
    }

    private function makeOrderId(Registration $registration): string
    {
        $attempt = $registration->payments()->count() + 1;

        return sprintf('ICOMAN-REG-%d-%d', $registration->id, $attempt);
    }
}
