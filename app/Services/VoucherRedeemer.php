<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penukaran voucher registrasi pada invoice yang belum dibayar.
 *
 * Diskon berlaku pada biaya registrasi dasar; add-on Jurnal SINTA 3 tetap
 * ditagih. Bila sisa tagihan menjadi nol, registrasi langsung ditandai lunas
 * dengan metode `waived` — bukan `gateway` — supaya laporan keuangan tidak
 * mencatat transaksi BorderPay Rp 0 yang tidak pernah terjadi.
 */
class VoucherRedeemer
{
    public function redeem(Registration $registration, string $code): Voucher
    {
        return DB::transaction(function () use ($registration, $code): Voucher {
            $voucher = Voucher::query()
                ->code($code)
                ->where('edition_id', $registration->edition_id)
                ->lockForUpdate()
                ->first();

            // Kode salah dan kode milik edisi lain sengaja memberi pesan sama:
            // jangan bocorkan kode mana yang benar-benar ada.
            if (! $voucher) {
                $this->fail('Kode voucher tidak dikenali.', 'That voucher code is not recognised.');
            }

            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            $this->assertRegistrationEligible($locked, $voucher);
            $this->assertVoucherUsable($voucher);

            if ($voucher->redemptions()->where('author_id', $locked->author_id)->exists()) {
                $this->fail('Akun Anda sudah memakai kode ini.', 'Your account has already used this code.');
            }

            $price = $locked->priceDetails();

            if ($price['legacy'] ?? false) {
                $this->fail(
                    'Invoice ini dibuat dengan skema lama. Hubungi panitia untuk memakai voucher.',
                    'This invoice uses an archived pricing scheme. Contact the committee to apply a voucher.',
                );
            }

            $base = (int) ($price['base_amount'] ?? 0);
            $addon = (int) ($price['addon_amount'] ?? 0);
            $discount = $voucher->discountFor($base);
            if ($discount < 1) {
                $this->fail('Nilai diskon voucher tidak valid.', 'This voucher has an invalid discount value.');
            }
            $payable = max(0, $base + $addon - $discount);

            $price['discount_amount'] = $discount;
            $price['voucher_code'] = $voucher->code;
            $price['voucher_host'] = $voucher->host_name;
            $price['voucher_type'] = $voucher->type;

            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'registration_id' => $locked->id,
                'author_id' => $locked->author_id,
                'discount_amount' => $discount,
                'redeemed_at' => now(),
            ]);

            $locked->forceFill([
                'voucher_id' => $voucher->id,
                'discount_amount' => $discount,
                'amount' => $payable,
                'pricing_snapshot' => $price,
            ]);

            if ($payable === 0) {
                $locked->forceFill([
                    'payment_method' => 'waived',
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            $locked->save();

            return $voucher;
        });
    }

    /**
     * Melepas slot yang telantar atau salah pakai. Hanya untuk registrasi yang
     * belum pernah menghasilkan uang masuk: kalau sisa tagihan sudah dibayar
     * lewat BorderPay, pembatalannya urusan panitia, bukan tombol di admin.
     */
    public function release(VoucherRedemption $redemption): void
    {
        DB::transaction(function () use ($redemption): void {
            // Same lock order as redeem(): voucher first, invoice second.
            Voucher::whereKey($redemption->voucher_id)->lockForUpdate()->firstOrFail();
            $registration = Registration::whereKey($redemption->registration_id)->lockForUpdate()->firstOrFail();

            if ((! $registration->isWaived() && $registration->status === 'paid')
                || $registration->paidAmount() > 0
                || $registration->hasUnresolvedPayment()) {
                $this->fail(
                    'Registrasi ini memiliki transaksi pembayaran. Rekonsiliasi bersama panitia keuangan sebelum melepas voucher.',
                    'This registration has a payment transaction. Reconcile it with the finance team before releasing the voucher.',
                );
            }

            $price = $registration->priceDetails();
            unset($price['discount_amount'], $price['voucher_code'], $price['voucher_host'], $price['voucher_type']);

            $registration->forceFill([
                'voucher_id' => null,
                'discount_amount' => 0,
                'amount' => (int) ($price['base_amount'] ?? 0) + (int) ($price['addon_amount'] ?? 0),
                'pricing_snapshot' => $price,
                'payment_method' => 'gateway',
                'status' => 'pending',
                'paid_at' => null,
            ])->save();

            $redemption->delete();
        });
    }

    private function assertRegistrationEligible(Registration $registration, Voucher $voucher): void
    {
        $audience = $registration->registrationFee?->audience;
        $category = $registration->registrationFee?->registrant_category;

        if (! in_array($audience, ['presenter', 'participant'], true)
            || ($voucher->audience !== 'all' && $voucher->audience !== $audience)
            || ($voucher->registrant_category !== 'all' && $voucher->registrant_category !== $category)
            || (in_array($voucher->type, ['cohost', 'free_paper'], true) && $registration->submission_id === null)) {
            $this->fail(
                'Voucher ini tidak berlaku untuk kategori registrasi Anda.',
                'This voucher does not apply to your registration category.',
            );
        }

        if ($registration->voucher_id !== null) {
            $this->fail(
                'Invoice ini sudah memakai voucher.',
                'This invoice already has a voucher applied.',
            );
        }

        if (! $registration->isPayable()) {
            $this->fail(
                'Voucher hanya bisa dipakai selama pembayaran belum selesai.',
                'A voucher can only be applied while the payment is still outstanding.',
            );
        }

        if ($registration->hasUnresolvedPayment()) {
            $this->fail(
                'Ada transaksi pembayaran yang masih berjalan. Periksa status pembayaran dulu sebelum memakai voucher.',
                'A payment is still in progress. Check the payment status before applying a voucher.',
            );
        }
    }

    private function assertVoucherUsable(Voucher $voucher): void
    {
        if (! $voucher->is_active) {
            $this->fail('Kode voucher ini sudah dinonaktifkan.', 'That voucher code has been deactivated.');
        }

        if ($voucher->isExpired()) {
            $this->fail('Kode voucher ini sudah kedaluwarsa.', 'That voucher code has expired.');
        }

        if ($voucher->remainingSlots() < 1) {
            $this->fail(
                'Kuota voucher ini sudah habis terpakai.',
                'This voucher has no remaining slots.',
            );
        }
    }

    private function fail(string $id, string $en): never
    {
        throw ValidationException::withMessages([
            'voucher_code' => app()->getLocale() === 'id' ? $id : $en,
        ]);
    }
}
