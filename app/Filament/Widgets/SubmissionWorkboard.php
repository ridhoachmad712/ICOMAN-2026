<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Submission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Papan kerja panitia: apa yang menunggu dikerjakan hari ini.
 *
 * Dashboard sebelumnya hanya melaporkan keadaan — total, diagram, lima baris
 * terakhir — dan tidak satu pun bisa diklik. Admin membacanya, lalu tetap harus
 * mencari sendiri pekerjaannya di menu Submissions.
 *
 * Empat angka di sini menjawab "bolanya di tangan siapa", dan masing-masing
 * membuka daftar yang sudah tersaring. Definisi antreannya milik model, sama
 * persis dengan yang dipakai tab di halaman Submissions.
 */
class SubmissionWorkboard extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected ?string $heading = 'Aktivitas Submission';

    protected ?string $description = 'Yang menunggu panitia, bukan sekadar hitungan. Klik untuk membuka daftarnya.';

    public static function canView(): bool
    {
        return auth()->user()?->managesSubmissions() ?? false;
    }

    protected function getStats(): array
    {
        $awaitingReviewer = Submission::query()->ofCurrentEdition()->awaitingReviewer()->count();
        $underReview = Submission::query()->ofCurrentEdition()->underReview()->count();
        $awaitingDecision = Submission::query()->ofCurrentEdition()->awaitingDecision()->count();
        $awaitingLoa = Submission::query()->ofCurrentEdition()->awaitingLoa()->count();

        return [
            Stat::make('Menunggu Reviewer', (string) $awaitingReviewer)
                ->description($awaitingReviewer > 0 ? 'Belum ditugaskan ke siapa pun' : 'Semua sudah ditugaskan')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($awaitingReviewer > 0 ? 'danger' : 'gray')
                ->url($this->tab('action')),

            Stat::make('Sedang Dinilai', (string) $underReview)
                ->description($this->oldestWaitLabel())
                ->descriptionIcon('heroicon-m-clock')
                ->color($underReview > 0 ? 'info' : 'gray')
                ->url($this->tab('under_review')),

            Stat::make('Menunggu Keputusan', (string) $awaitingDecision)
                ->description($awaitingDecision > 0 ? 'Penilaian selesai, tinggal diputuskan' : 'Tidak ada yang menggantung')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($awaitingDecision > 0 ? 'warning' : 'gray')
                ->url($this->tab('action')),

            Stat::make('LOA Belum Terbit', (string) $awaitingLoa)
                ->description($awaitingLoa > 0 ? 'Sudah diterima, suratnya belum' : 'Semua LOA sudah terbit')
                ->descriptionIcon('heroicon-m-document-check')
                ->color($awaitingLoa > 0 ? 'warning' : 'gray')
                ->url($this->tab('action')),
        ];
    }

    private function tab(string $tab): string
    {
        return SubmissionResource::getUrl('index').'?activeTab='.$tab;
    }

    /**
     * Berapa lama paper yang paling lama menunggu sudah menunggu.
     *
     * Angka "sedang dinilai" saja tidak memberi tahu apakah keadaannya sehat.
     * Tiga paper yang masuk kemarin dan tiga yang menggantung sebulan terlihat
     * sama persis tanpa kalimat ini.
     */
    private function oldestWaitLabel(): string
    {
        $oldest = Submission::query()
            ->ofCurrentEdition()
            ->underReview()
            ->whereNotNull('submitted_at')
            ->min('submitted_at');

        if (! $oldest) {
            return 'Tidak ada yang sedang dinilai';
        }

        $days = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($oldest)->startOfDay());

        return match (true) {
            $days <= 0 => 'Terlama: masuk hari ini',
            $days === 1 => 'Terlama: menunggu 1 hari',
            default => 'Terlama: menunggu '.$days.' hari',
        };
    }
}
