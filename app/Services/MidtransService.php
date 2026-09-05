<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Midtrans\Config;

class MidtransService
{
    private string $serverKey;

    public function __construct(private MidtransGateway $gateway)
    {
        $key = rescue(fn () => siteSettings()->midtrans_server_key, null, false);
        $this->serverKey = filled($key) ? $key : (string) config('services.midtrans.server_key');
        Config::$serverKey = $this->serverKey;
        Config::$isProduction = filled($key)
            ? (bool) rescue(fn () => siteSettings()->midtrans_is_production, false, false)
            : (bool) config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function isConfigured(): bool
    {
        return filled($this->serverKey);
    }

    public function createSnapRedirect(Registration $registration): string
    {
        app(ConferenceDeadlines::class)->assertOpen('payment', $registration->edition_id);
        // Commit the immutable order before the external request; concurrent tabs reuse it.
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
                'method' => 'gateway', 'gateway_name' => 'midtrans',
                'gateway_reference' => 'ICOMAN-'.$registration->id.'-'.Str::ulid(),
                'amount' => $registration->amount, 'status' => 'initiated',
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
        $author = $registration->author;
        $result = $this->gateway->create([
            'transaction_details' => ['order_id' => $payment->gateway_reference, 'gross_amount' => (int) $payment->amount],
            'customer_details' => ['first_name' => $author?->name, 'email' => $author?->email, 'phone' => $author?->phone],
            'item_details' => [['id' => 'reg-'.$registration->id, 'price' => (int) $payment->amount, 'quantity' => 1, 'name' => 'Conference registration']],
            'callbacks' => ['finish' => route('payment.midtrans.finish')],
        ]);
        $url = (string) ($result->redirect_url ?? '');
        if (! str_starts_with($url, 'https://') || ! in_array(parse_url($url, PHP_URL_HOST), ['app.midtrans.com', 'app.sandbox.midtrans.com'], true)) {
            throw new \RuntimeException('Invalid gateway checkout URL.');
        }
        $payment->update(['checkout_url' => $url, 'raw_response' => (array) $result]);

        return $url;
    }

    public function synchronize(Registration $registration): void
    {
        foreach ($registration->payments()->where('method', 'gateway')->where('status', 'initiated')->get() as $payment) {
            $payload = $this->gateway->status($payment->gateway_reference);
            if (($payload['order_id'] ?? null) !== $payment->gateway_reference) {
                throw new \RuntimeException('Gateway status did not identify the expected order.');
            }
            $this->applyNotification($payload);
        }
    }

    public function verifySignature(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $key) {
            if (! isset($payload[$key]) || ! is_scalar($payload[$key])) {
                return false;
            }
        }
        if ($this->serverKey === '') {
            return false;
        }

        return hash_equals(hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$this->serverKey), (string) $payload['signature_key']);
    }

    public function applyNotification(array $payload): ?Registration
    {
        $order = $payload['order_id'] ?? null;
        $amount = $payload['gross_amount'] ?? null;
        if (! is_string($order) || ! is_numeric($amount)) {
            return null;
        }
        $candidate = Payment::where('gateway_reference', $order)->where('gateway_name', 'midtrans')->where('method', 'gateway')->first();
        if (! $candidate) {
            return null;
        }

        return DB::transaction(function () use ($candidate, $payload, $amount): ?Registration {
            $registration = Registration::whereKey($candidate->registration_id)->lockForUpdate()->first();
            $payment = Payment::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $registration || ! $payment || number_format((float) $payment->amount, 2, '.', '') !== number_format((float) $amount, 2, '.', '')) {
                return null;
            }
            $status = $payload['transaction_status'] ?? '';
            $success = in_array($status, ['capture', 'settlement'], true) && ($payload['fraud_status'] ?? 'accept') === 'accept';
            $failure = in_array($status, ['deny', 'cancel', 'expire'], true);
            $history = $payment->notification_history ?? [];
            $fingerprint = hash('sha256', json_encode($payload));
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
                    $registration->update(['status' => $matchesInvoice ? 'paid' : 'pending_verification', 'paid_at' => $matchesInvoice ? now() : null, 'gateway_payload' => $payload]);
                    if (! $matchesInvoice) {
                        Log::warning('Payment received against a different invoice amount; reconcile manually.', ['registration_id' => $registration->id, 'payment_id' => $payment->id]);
                    }
                } elseif ($failure && $registration->gateway_transaction_id === $payment->gateway_reference && $registration->status !== 'pending_verification') {
                    $registration->update(['status' => 'failed', 'gateway_payload' => $payload]);
                }
            }

            return $registration->refresh();
        });
    }
}
