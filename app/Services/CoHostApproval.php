<?php

namespace App\Services;

use App\Models\CoHost;
use App\Models\Registration;
use App\Models\RegistrationFee;
use App\Models\Sponsor;
use App\Models\Voucher;
use App\Notifications\CoHostApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyetujui pengajuan co-host: menerbitkan invoice kemitraan, menyiapkan
 * voucher, dan mendaftarkan institusinya sebagai partner.
 *
 * Voucher dan logo sengaja belum menyala di sini. Keduanya baru hidup setelah
 * biaya kemitraannya lunas — lihat Registration::booted(), supaya berlaku lewat
 * jalur pembayaran mana pun.
 */
class CoHostApproval
{
    public function approve(CoHost $coHost, ?int $reviewerId = null): CoHost
    {
        return DB::transaction(function () use ($coHost, $reviewerId): CoHost {
            $locked = CoHost::whereKey($coHost->id)->lockForUpdate()->firstOrFail();

            if ($locked->isApproved()) {
                return $locked;
            }

            $fee = $this->partnershipFee($locked);

            $locked->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
                'rejection_reason' => null,
                'voucher_id' => $this->makeVoucher($locked)->id,
                'sponsor_id' => $this->makeSponsor($locked)->id,
            ]);

            $this->makeInvoice($locked, $fee);

            $locked->author?->notify(new CoHostApproved($locked->fresh()));

            return $locked->fresh();
        });
    }

    public function reject(CoHost $coHost, string $reason, ?int $reviewerId = null): CoHost
    {
        $coHost->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewerId,
            'rejection_reason' => $reason,
        ]);

        return $coHost->fresh();
    }

    /**
     * Tarif kemitraan diambil dari Registration Fees (audience `cohost`).
     * Tanpa itu panitia tidak punya angka untuk ditagihkan, jadi persetujuan
     * ditahan dengan pesan yang jelas alih-alih menerbitkan invoice nol.
     */
    private function partnershipFee(CoHost $coHost): RegistrationFee
    {
        $fee = RegistrationFee::where('edition_id', $coHost->edition_id)
            ->where('audience', 'cohost')
            ->orderBy('order')
            ->first();

        if (! $fee) {
            throw ValidationException::withMessages([
                'fee' => app()->getLocale() === 'id'
                    ? 'Tarif kemitraan co-host belum ditetapkan. Tambahkan dulu di Submission → Registration Fees dengan audience "cohost".'
                    : 'The co-host partnership fee has not been set. Add it first under Submission → Registration Fees with audience "cohost".',
            ]);
        }

        return $fee;
    }

    private function makeInvoice(CoHost $coHost, RegistrationFee $fee): Registration
    {
        $existing = $coHost->registration();

        if ($existing) {
            return $existing;
        }

        $quote = $fee->quote();

        return Registration::create([
            'edition_id' => $coHost->edition_id,
            'author_id' => $coHost->author_id,
            'registration_fee_id' => $fee->id,
            'payment_method' => 'gateway',
            'amount' => $quote['base_amount'],
            'pricing_snapshot' => $quote,
            'status' => 'pending',
        ]);
    }

    private function makeVoucher(CoHost $coHost): Voucher
    {
        if ($coHost->voucher) {
            return $coHost->voucher;
        }

        return Voucher::create([
            'edition_id' => $coHost->edition_id,
            'code' => $this->uniqueCode(),
            'host_name' => $coHost->institution_name,
            'quota' => CoHost::FREE_PAPERS,
            // Menyala setelah biaya kemitraannya lunas.
            'is_active' => false,
        ]);
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'COHOST-'.strtoupper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    private function makeSponsor(CoHost $coHost): Sponsor
    {
        if ($coHost->sponsor) {
            return $coHost->sponsor;
        }

        $sponsor = Sponsor::create([
            'edition_id' => $coHost->edition_id,
            'name' => $coHost->institution_name,
            'tier' => 'partner',
            'website_url' => $coHost->website,
            'order' => (int) Sponsor::where('edition_id', $coHost->edition_id)->max('order') + 1,
            // Tampil di website setelah kemitraannya berjalan penuh.
            'is_published' => false,
        ]);

        // Logo yang diunggah saat mendaftar dipakai ulang, tidak diminta dua kali.
        if ($logo = $coHost->getFirstMedia('logo')) {
            $logo->copy($sponsor, 'logo', 'public');
        }

        return $sponsor;
    }
}
