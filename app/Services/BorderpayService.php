<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Integrasi pembayaran BorderPay (menggantikan Kasera Pay).
 *
 * Satu keputusan membentuk seluruh berkas ini: **isi webhook BorderPay tidak
 * pernah dipercaya.**
 *
 * Webhook mereka tidak ditandatangani. Verifikasi yang didokumentasikan hanya
 * mencocokkan token statis di header `x-borderpay-token`; tidak ada HMAC, tidak
 * ada timestamp (diperiksa terhadap berkas OpenAPI resminya: nol kemunculan
 * untuk signature, hmac, sha256, dan timestamp). Artinya siapa pun yang pernah
 * memperoleh token itu dapat mengirim "payment.paid" palsu untuk nomor order
 * mana pun.
 *
 * Karena itu webhook di sini diperlakukan hanya sebagai isyarat "ada yang
 * berubah pada order ini". Statusnya selalu ditanyakan ulang lewat
 * GET /payments/{reference}, dan jawaban itulah yang dipakai. Payload palsu
 * menjadi tidak berguna: paling jauh ia memancing satu panggilan status.
 */
class BorderpayService
{
    private string $apiKey;

    private string $webhookToken;

    public function __construct(private BorderpayGateway $gateway)
    {
        $key = rescue(fn () => siteSettings()->borderpay_api_key, null, false);
        $this->apiKey = filled($key) ? $key : (string) config('services.borderpay.api_key');

        $token = rescue(fn () => siteSettings()->borderpay_webhook_token, null, false);
        $this->webhookToken = filled($token) ? $token : (string) config('services.borderpay.webhook_token');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /** Test mode dikenali dari prefix key-nya, bukan dari saklar tersendiri. */
    public function isLiveMode(): bool
    {
        return str_starts_with($this->apiKey, 'bp_live_');
    }

    public function createCheckoutRedirect(Registration $registration, bool $installment = false): string
    {
        app(ConferenceDeadlines::class)->assertOpen('payment', $registration->edition_id);

        // Order dikunci sebelum request keluar; tab kedua memakai ulang baris ini.
        [$registration, $payment, $isNew] = DB::transaction(function () use ($registration, $installment): array {
            $registration = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($registration->isPayable(), 403);
            abort_unless($registration->priceDetails()['currency'] === 'IDR', 422);

            // Pilihan cara membayar diperbarui di bawah kunci yang sama dengan
            // ordernya. Selama belum ada yang dibayar author boleh berganti
            // pikiran; sesudah itu allowsInstallments() menutup pintunya.
            if ($registration->allowsInstallments()) {
                $registration->update(['installment_plan' => $installment]);
            }

            // Sisa tagihan nol berarti tidak ada lagi yang perlu dibayar.
            $due = $registration->amountDueNow();
            abort_if($due <= 0, 409);

            $existing = $registration->payments()->where('method', 'gateway')->where('status', 'initiated')->latest('id')->first();

            if ($existing) {
                // Dipakai ulang HANYA bila nominalnya masih sama, yaitu kasus
                // tab kedua yang menjadi alasan pengecekan ini ada. Nominal yang
                // berbeda berarti author mengubah pilihannya, dan memakai ulang
                // order lama akan menagih angka yang tidak ia pilih.
                $sameAmount = (float) $existing->amount === (float) $due;

                // Order milik gateway sebelumnya tidak pernah dipakai ulang.
                // Tanpa pagar ini, baris Kasera yang tertinggal akan terus
                // mengirim author ke halaman bayar Kasera — dan uang yang masuk
                // ke sana tidak akan pernah terbaca di sini, sebab statusnya
                // ditanyakan ke BorderPay.
                $sameGateway = $existing->gateway_name === 'borderpay';

                // Baris tanpa checkout_url berarti permintaan ke gateway tidak
                // pernah selesai. Jeda singkat diberikan untuk tab yang sedang
                // berjalan; lewat itu barisnya dilepas, sebab kalau tidak author
                // terkunci selamanya pada pesan "pembayaran sedang disiapkan".
                $usable = $existing->checkout_url !== null
                    || $existing->created_at?->greaterThan(now()->subMinutes(2));

                if ($sameGateway && $sameAmount && $usable) {
                    return [$registration, $existing, false];
                }

                $existing->update(['status' => 'failed']);
            }

            $payment = $registration->payments()->create([
                'method' => 'gateway',
                'gateway_name' => 'borderpay',
                // Sekaligus reference_id di BorderPay: itulah kunci idempotensi
                // sekaligus alamat untuk menanyakan statusnya nanti.
                'gateway_reference' => 'ICOMAN-'.$registration->id.'-'.Str::ulid(),
                'amount' => $due,
                'status' => 'initiated',
            ]);
            $registration->update(['gateway_transaction_id' => $payment->gateway_reference, 'status' => 'pending']);

            return [$registration, $payment, true];
        });

        if ($payment->checkout_url) {
            return $payment->checkout_url;
        }

        if (! $isNew) {
            throw ValidationException::withMessages(['payment' => app()->getLocale() === 'id'
                ? 'Pembayaran sedang disiapkan atau menunggu rekonsiliasi. Gunakan Periksa Status; hubungi panitia jika tetap belum tersedia.'
                : 'Checkout is being prepared or awaiting reconciliation. Use Check Status; contact the committee if it remains unavailable.']);
        }

        $created = $this->gateway->createPayment($this->apiKey, $this->checkoutBody($registration, $payment));

        $url = (string) ($created['pay_url'] ?? '');
        if (! str_starts_with($url, 'https://') || parse_url($url, PHP_URL_HOST) !== BorderpayGateway::CHECKOUT_HOST) {
            throw new \RuntimeException('Invalid gateway checkout URL.');
        }

        $payment->update([
            'checkout_url' => $url,
            'raw_response' => $created,
        ]);

        return $url;
    }

    /**
     * Isi permintaan pembuatan pembayaran.
     *
     * `method` sengaja tidak dikirim: tanpanya BorderPay membuat checkout
     * session dan authorlah yang memilih QRIS, virtual account, atau e-wallet
     * di halaman mereka. Metode baru yang mereka tambahkan langsung tersedia
     * tanpa kita deploy apa pun.
     *
     * @return array<string, mixed>
     */
    private function checkoutBody(Registration $registration, Payment $payment): array
    {
        return [
            'amount' => (int) $payment->amount,
            'reference_id' => $payment->gateway_reference,
            'return_url' => route('payment.borderpay.finish'),
        ];
    }

    /**
     * Menyelaraskan status dari BorderPay.
     *
     * Dipakai tombol "Periksa Status" dan juga oleh penanganan webhook.
     */
    public function synchronize(Registration $registration): void
    {
        $payments = $registration->payments()
            ->where('method', 'gateway')
            ->where('gateway_name', 'borderpay')
            ->where('status', 'initiated')
            ->get();

        foreach ($payments as $payment) {
            $this->refresh($payment->gateway_reference);
        }
    }

    /**
     * Memverifikasi pengirim webhook.
     *
     * Ini otentikasi pemanggil, BUKAN verifikasi payload: tokennya statis dan
     * tidak terikat pada isi kiriman. Karena itu ia hanya menjaga pintu, dan
     * status sebenarnya tetap ditanyakan ulang ke gateway.
     */
    public function verifyToken(?string $token): bool
    {
        if ($this->webhookToken === '' || blank($token)) {
            return false;
        }

        return hash_equals($this->webhookToken, $token);
    }

    /**
     * Menerima isyarat webhook.
     *
     * Dari seluruh payload hanya `reference_id` yang dipakai, dan itu pun
     * sekadar untuk tahu order mana yang harus ditanyakan. Nominal dan status
     * di dalam payload diabaikan.
     *
     * @param  array<string, mixed>  $event
     */
    public function applyEvent(array $event): ?Registration
    {
        $reference = $event['data']['reference_id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return $this->refresh($reference);
    }

    /**
     * Menanyakan status sebuah order ke gateway, lalu menerapkannya.
     *
     * Order yang tidak kita kenali tidak pernah ditanyakan: tanpa penjagaan itu
     * kiriman palsu bisa dipakai memancing panggilan keluar sebanyak-banyaknya.
     */
    public function refresh(string $reference): ?Registration
    {
        $payment = Payment::where('gateway_reference', $reference)
            ->where('gateway_name', 'borderpay')
            ->where('method', 'gateway')
            ->first();

        if (! $payment) {
            return null;
        }

        $remote = $this->gateway->getPayment($this->apiKey, $reference);

        if (($remote['reference_id'] ?? null) !== $reference) {
            throw new \RuntimeException('Gateway status did not identify the expected payment.');
        }

        return $this->settle($payment, $remote);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function settle(Payment $candidate, array $remote): ?Registration
    {
        $status = $remote['status'] ?? null;
        $amount = $remote['amount'] ?? null;

        if (! is_string($status) || ! is_numeric($amount)) {
            return null;
        }

        return DB::transaction(function () use ($candidate, $status, $amount, $remote): ?Registration {
            $registration = Registration::whereKey($candidate->registration_id)->lockForUpdate()->first();
            $payment = Payment::whereKey($candidate->id)->lockForUpdate()->first();

            if (! $registration || ! $payment) {
                return null;
            }

            // Nominal yang tidak sama dengan order yang kita buat bukan pembayaran ini.
            if (number_format((float) $payment->amount, 2, '.', '') !== number_format((float) $amount, 2, '.', '')) {
                return null;
            }

            $success = $status === 'paid';
            $failure = in_array($status, ['expired', 'failed'], true);

            $history = $payment->notification_history ?? [];
            $fingerprint = hash('sha256', json_encode($remote));
            if (! collect($history)->contains('fingerprint', $fingerprint)) {
                $history[] = ['received_at' => now()->toIso8601String(), 'fingerprint' => $fingerprint, 'payload' => $remote];
            }
            $payment->notification_history = $history;

            if ($payment->status !== 'success') {
                $payment->status = $success ? 'success' : ($failure ? 'failed' : $payment->status);
                $payment->raw_response = $remote;
            }
            $payment->save();

            if ($registration->status !== 'paid') {
                if ($success) {
                    // Yang menentukan lunas adalah jumlah seluruh pembayaran yang
                    // berhasil, bukan satu pembayaran terhadap total tagihan:
                    // di bawah cicilan, satu pembayaran memang lebih kecil.
                    $received = $registration->paidAmount();
                    $total = (float) $registration->amount;

                    if ($received > $total) {
                        $registration->update(['status' => 'pending_verification', 'paid_at' => null, 'gateway_payload' => $remote]);
                        Log::warning('Payments exceed the invoice amount; reconcile manually.', [
                            'registration_id' => $registration->id,
                            'payment_id' => $payment->id,
                        ]);
                    } elseif ($received >= $total) {
                        $registration->update(['status' => 'paid', 'paid_at' => now(), 'gateway_payload' => $remote]);
                    } else {
                        // Cicilan pertama: uangnya tercatat, tapi registrasinya
                        // belum lunas sehingga gerbang unggah full paper tetap tertutup.
                        $registration->update(['gateway_payload' => $remote]);
                    }
                } elseif ($failure
                    && $registration->paidAmount() <= 0
                    && $registration->gateway_transaction_id === $payment->gateway_reference
                    && $registration->status !== 'pending_verification') {
                    $registration->update(['status' => 'failed', 'gateway_payload' => $remote]);
                }
            }

            return $registration->refresh();
        });
    }
}
