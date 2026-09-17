<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penukaran kode voucher co-host pada invoice presenter.
 *
 * Voucher membebaskan biaya registrasi dasar; add-on Jurnal SINTA 3 tetap
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

            $this->assertRegistrationEligible($locked);
            $this->assertVoucherUsable($voucher);

            $price = $locked->priceDetails();

            if ($price['legacy'] ?? false) {
                $this->fail(
                    'Invoice ini dibuat dengan skema lama. Hubungi panitia untuk memakai voucher.',
                    'This invoice uses an archived pricing scheme. Contact the committee to apply a voucher.',
                );
            }

            $base = (int) ($price['base_amount'] ?? 0);
            $addon = (int) ($price['addon_amount'] ?? 0);
            $discount = $base; // voucher menanggung biaya dasar, bukan add-on
            $payable = max(0, $base + $addon - $discount);

            $price['discount_amount'] = $discount;
            $price['voucher_code'] = $voucher->code;
            $price['voucher_host'] = $voucher->host_name;

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
            $registration = Registration::whereKey($redemption->registration_id)->lockForUpdate()->firstOrFail();

            if (! $registration->isWaived() && $registration->status === 'paid') {
                $this->fail(
                    'Registrasi ini sudah dibayar sebagian lewat BorderPay. Lepas slotnya secara manual bersama panitia keuangan.',
                    'This registration was partly paid through BorderPay. Release the slot together with the finance team.',
                );
            }

            $price = $registration->priceDetails();
            unset($price['discount_amount'], $price['voucher_code'], $price['voucher_host']);

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

    private function assertRegistrationEligible(Registration $registration): void
    {
        if ($registration->submission_id === null) {
            $this->fail(
                'Voucher co-host hanya berlaku untuk pengiriman paper (presenter).',
                'Co-host vouchers only apply to paper submissions (presenters).',
            );
        }

        if ($registration->voucher_id !== null) {
            $this->fail(
                'Invoice ini sudah memakai voucher.',
                'This invoice already has a voucher applied.',
            );
        }

        if (! in_array($registration->status, ['pending', 'failed'], true)) {
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
