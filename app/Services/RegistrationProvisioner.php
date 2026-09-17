<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Registration;
use App\Models\RegistrationFee;
use Illuminate\Support\Facades\DB;

/**
 * Membuat invoice registrasi secara OTOMATIS berdasarkan kategori yang sudah
 * dipilih author saat mendaftar — author tidak perlu mengisi form registrasi,
 * cukup melanjutkan ke pembayaran. Idempotent: mengembalikan registrasi aktif
 * yang sudah ada bila tersedia, tanpa membuat duplikat.
 */
class RegistrationProvisioner
{
    public function ensureFor(Author $author): ?Registration
    {
        return DB::transaction(function () use ($author) {
            // Serialize checkout for this author, including concurrent tabs.
            $locked = Author::whereKey($author->id)->lockForUpdate()->firstOrFail();

            return $this->provision($locked);
        });
    }

    private function provision(Author $author): ?Registration
    {
        $edition = currentEdition();

        if (! $edition || ! $author->participation_type) {
            return null;
        }

        $audience = $author->isPresenter() ? 'presenter' : 'participant';

        // Presenter hanya bisa dibuatkan invoice setelah papernya accepted & LOA terbit.
        $submission = null;
        if ($author->isPresenter()) {
            $submission = $author->submissions()
                ->where('edition_id', $edition->id)
                ->where('status', 'accepted')
                ->whereNotNull('loa_issued_at')
                ->latest('submitted_at')
                ->first();

            if (! $submission) {
                return null;
            }
        }

        // Sudah ada registrasi aktif untuk jalur ini → pakai itu (jangan duplikat).
        $existing = $author->registrations()
            ->where('edition_id', $edition->id)
            ->when(
                $submission,
                fn ($query) => $query->where('submission_id', $submission->id),
                fn ($query) => $query->whereNull('submission_id'),
            )
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($submission && $orphan = $this->orphanedPresenterInvoice($author, $edition->id)) {
            $orphan->update(['submission_id' => $submission->id]);

            return $orphan->refresh();
        }

        app(ConferenceDeadlines::class)->assertOpen('payment', $edition->id);

        // Tarif mengikuti kategori (registrant_category) yang dipilih saat mendaftar.
        $fee = RegistrationFee::query()
            ->where('edition_id', $edition->id)
            ->where('audience', $audience)
            ->where('registrant_category', $author->feeCategory())
            ->get();

        if ($fee->count() !== 1) {
            return null;
        }
        $fee = $fee->first();
        $quote = $fee->quote();

        return Registration::create([
            'edition_id' => $edition->id,
            'author_id' => $author->id,
            'registration_fee_id' => $fee->id,
            'submission_id' => $submission?->id,
            // Semua pembayaran melalui BorderPay.
            'payment_method' => 'gateway',
            'amount' => $quote['base_amount'],
            'pricing_snapshot' => $quote,
            'status' => 'pending',
        ]);
    }

    /**
     * Invoice presenter yang kehilangan tautan papernya.
     *
     * `registrations.submission_id` memakai nullOnDelete, jadi paper yang
     * dihapus lalu dikirim ulang meninggalkan invoice tanpa paper. Invoice
     * seperti itu tidak akan pernah menampilkan pilihan jurnal SINTA 3 —
     * tawarannya melekat pada paper — dan membiarkannya berarti menerbitkan
     * tagihan kedua untuk orang yang sama. Maka diambil alih, bukan ditinggal.
     *
     * Yang sudah lunas tidak pernah diambil alih: memindahkannya akan membuat
     * paper baru langsung terhitung terbayar.
     */
    private function orphanedPresenterInvoice(Author $author, int $editionId): ?Registration
    {
        return $author->registrations()
            ->where('edition_id', $editionId)
            ->whereNull('submission_id')
            ->where('status', '!=', 'paid')
            ->whereHas('registrationFee', fn ($fee) => $fee->where('audience', 'presenter'))
            ->latest()
            ->first();
    }
}
