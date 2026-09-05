<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Registration;
use App\Models\RegistrationFee;

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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($author) {
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
            // Semua pembayaran melalui Midtrans.
            'payment_method' => 'gateway',
            'amount' => $quote['base_amount'],
            'pricing_snapshot' => $quote,
            'status' => 'pending',
        ]);
    }
}
