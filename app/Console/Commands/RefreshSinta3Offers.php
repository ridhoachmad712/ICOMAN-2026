<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;

/**
 * Menyelaraskan tawaran SINTA 3 dengan rekomendasi reviewer.
 *
 * Sebelum perbaikan ini, `sinta3_offered` hanya dihitung sekali — pada detik
 * LOA pertama terbit. Rekomendasi yang tercatat sesudahnya tidak pernah sampai
 * ke author, dan tidak ada kontrol di panel untuk membukanya. Perintah ini
 * membereskan paper yang tertinggal itu sekaligus.
 *
 * Paper yang tawarannya sudah ditetapkan panitia sengaja dilewati: keputusan
 * mereka tidak boleh ditimpa oleh perintah perawatan.
 */
class RefreshSinta3Offers extends Command
{
    protected $signature = 'icoman:refresh-sinta3 {--fix : Terapkan perubahannya; tanpa ini hanya melaporkan}';

    protected $description = 'Selaraskan tawaran Jurnal SINTA 3 dengan rekomendasi reviewer.';

    public function handle(): int
    {
        $apply = (bool) $this->option('fix');
        $opened = [];
        $closed = [];
        $skipped = 0;

        Submission::with('reviewAssignments.review')->chunkById(200, function ($submissions) use ($apply, &$opened, &$closed, &$skipped): void {
            foreach ($submissions as $submission) {
                if ($submission->sinta3_offer_overridden_at !== null) {
                    $skipped++;

                    continue;
                }

                $recommended = $submission->reviewsRecommendSinta3();

                if ($recommended === (bool) $submission->sinta3_offered) {
                    continue;
                }

                $line = $submission->submission_number.' — '.$submission->title;
                $recommended ? $opened[] = $line : $closed[] = $line;

                if ($apply) {
                    $submission->refreshSinta3Offer();
                }
            }
        });

        $this->report('Tawaran dibuka (reviewer merekomendasikan)', $opened);
        $this->report('Tawaran ditutup (tidak ada rekomendasi)', $closed);

        if ($skipped > 0) {
            $this->line($skipped.' paper dilewati karena tawarannya sudah ditetapkan panitia.');
        }

        $total = count($opened) + count($closed);

        if ($total === 0) {
            $this->info('Semua tawaran sudah selaras. Tidak ada yang perlu diubah.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line($apply
            ? $total.' paper diperbarui.'
            : $total.' paper perlu diperbarui. Jalankan ulang dengan --fix untuk menerapkannya.');

        return self::SUCCESS;
    }

    /** @param  array<int, string>  $lines */
    private function report(string $heading, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $this->newLine();
        $this->info($heading.' ('.count($lines).'):');

        foreach ($lines as $line) {
            $this->line('  '.$line);
        }
    }
}
