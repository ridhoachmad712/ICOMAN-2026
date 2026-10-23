<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Certificate;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menerbitkan sertifikat sesuai rancangan yang disepakati panitia:
 *
 * - Certificate of Presentation: SATU PER PENULIS paper (termasuk co-author),
 *   bila invoice presenter lunas dan full paper sudah masuk. Semuanya
 *   diunduh lewat akun pengirim paper.
 * - Certificate of Participation: satu per peserta seminar yang lunas.
 *
 * Kehadiran tidak dijadikan syarat. Penerbitan dijalankan panitia (tombol
 * "Terbitkan"), dan dapat diulang: yang sudah punya sertifikat, termasuk yang
 * sudah dicabut, tidak diterbitkan lagi.
 */
class CertificateIssuer
{
    /**
     * Calon sertifikat yang belum terbit, sebagai atribut siap simpan
     * (tanpa nomor, token, dan waktu terbit).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pending(Edition $edition): Collection
    {
        return $this->pendingPresentations($edition)->concat($this->pendingParticipations($edition))->values();
    }

    /** @return array{presentation: int, participation: int} */
    public function pendingCounts(Edition $edition): array
    {
        $pending = $this->pending($edition);

        return [
            'presentation' => $pending->where('type', 'presentation')->count(),
            'participation' => $pending->where('type', 'participation')->count(),
        ];
    }

    /** Menerbitkan semua yang memenuhi syarat dan belum punya sertifikat. */
    public function issuePending(Edition $edition, ?User $issuer = null): int
    {
        return DB::transaction(function () use ($edition, $issuer): int {
            // Dua admin yang menekan bersamaan tidak boleh menerbitkan ganda.
            Edition::whereKey($edition->id)->lockForUpdate()->first();

            $count = 0;
            foreach ($this->pending($edition) as $attributes) {
                // Nomor dihitung lebih dulu: number() juga membuang kunci bantu
                // (submission_number, position) dari $attributes.
                $number = $this->number($edition, $attributes);

                Certificate::create(array_merge($attributes, [
                    'edition_id' => $edition->id,
                    'number' => $number,
                    'verification_token' => Certificate::newToken(),
                    'issued_at' => now(),
                    'issued_by' => $issuer?->id,
                ]));
                $count++;
            }

            return $count;
        });
    }

    public function revoke(Certificate $certificate, string $reason): void
    {
        $certificate->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
    }

    /**
     * Terbit ulang setelah koreksi data (mis. salah nama): nama, afiliasi, dan
     * judul diambil ulang dari data terkini, nomornya tetap, dan kode QR
     * diganti sehingga cetakan lama tidak lagi lolos verifikasi.
     */
    public function reissue(Certificate $certificate, ?User $issuer = null): Certificate
    {
        $certificate->loadMissing(['submissionAuthor', 'author', 'submission']);
        $source = $certificate->submissionAuthor ?? $certificate->author;

        $certificate->update([
            'recipient_name' => $source?->name ?? $certificate->recipient_name,
            'recipient_affiliation' => $source?->affiliation ?? $certificate->recipient_affiliation,
            'paper_title' => $certificate->submission?->title ?? $certificate->paper_title,
            'verification_token' => Certificate::newToken(),
            'issued_at' => now(),
            'issued_by' => $issuer?->id,
            'revoked_at' => null,
            'revoke_reason' => null,
        ]);

        return $certificate;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function pendingPresentations(Edition $edition): Collection
    {
        $issued = Certificate::query()
            ->where('edition_id', $edition->id)
            ->where('type', 'presentation')
            ->get(['submission_id', 'submission_author_id']);

        return Submission::query()
            ->where('edition_id', $edition->id)
            ->where('status', 'accepted')
            ->withFullPaper()
            ->whereHas('author', fn (Builder $author) => $this->paidIn($author, $edition))
            ->with(['author', 'authors'])
            ->orderBy('submission_number')
            ->get()
            ->flatMap(function (Submission $submission) use ($issued): array {
                $writers = $submission->authors->sortBy('order')->values();

                // Paper tanpa daftar penulis (data lama): pengirimnya yang tercantum.
                $recipients = $writers->isNotEmpty()
                    ? $writers->map(fn ($writer, $index) => [$writer->id, $writer->name, $writer->affiliation, $index + 1])
                    : collect([[null, $submission->author?->name, $submission->author?->affiliation, 1]]);

                return $recipients
                    ->reject(fn (array $recipient) => $issued->contains(fn (Certificate $certificate) => $certificate->submission_id === $submission->id
                        && $certificate->submission_author_id === $recipient[0]))
                    ->map(fn (array $recipient) => [
                        'type' => 'presentation',
                        'author_id' => $submission->author_id,
                        'submission_id' => $submission->id,
                        'submission_author_id' => $recipient[0],
                        'recipient_name' => (string) $recipient[1],
                        'recipient_affiliation' => $recipient[2],
                        'paper_title' => $submission->title,
                        'position' => $recipient[3],
                        'submission_number' => $submission->submission_number,
                    ])
                    ->all();
            })
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function pendingParticipations(Edition $edition): Collection
    {
        return Author::query()
            ->where('participation_type', 'participant')
            ->where(fn (Builder $author) => $this->paidIn($author, $edition))
            ->whereDoesntHave('certificates', fn (Builder $certificate) => $certificate
                ->where('edition_id', $edition->id)
                ->where('type', 'participation'))
            ->orderBy('name')
            ->get()
            ->map(fn (Author $author) => [
                'type' => 'participation',
                'author_id' => $author->id,
                'submission_id' => null,
                'submission_author_id' => null,
                'recipient_name' => $author->name,
                'recipient_affiliation' => $author->affiliation,
                'paper_title' => null,
            ]);
    }

    private function paidIn(Builder $author, Edition $edition): Builder
    {
        return $author->whereHas('registrations', fn (Builder $registration) => $registration
            ->where('edition_id', $edition->id)
            ->where('status', 'paid'));
    }

    /**
     * ICOMAN2026-001/P1 untuk penulis ke-1 paper ICOMAN2026-001;
     * ICOMAN2026/A/0001 untuk peserta seminar. Dipanggil di dalam kunci edisi.
     *
     * @param  array<string, mixed>  $attributes  kunci bantu penomoran dibuang di sini
     */
    private function number(Edition $edition, array &$attributes): string
    {
        if ($attributes['type'] === 'presentation') {
            $base = $attributes['submission_number'].'/P'.$attributes['position'];
            unset($attributes['submission_number'], $attributes['position']);

            return $this->unique($base);
        }

        $code = (string) Str::of($edition->name)->replaceMatches('/[^A-Za-z0-9]/', '')->upper();
        $prefix = $code.'/A/';
        $last = Certificate::query()
            ->where('number', 'like', $prefix.'%')
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $this->unique($prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT));
    }

    /** Pengaman terakhir bila nomor yang sama entah bagaimana sudah dipakai. */
    private function unique(string $number): string
    {
        $candidate = $number;
        for ($suffix = 2; Certificate::where('number', $candidate)->exists(); $suffix++) {
            $candidate = $number.'-'.$suffix;
        }

        return $candidate;
    }
}
