<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use App\Models\Submission;
use App\Settings\SiteSettings;
use Filament\Widgets\ChartWidget;

/**
 * Perjalanan paper dari masuk sampai lunas, dalam satu pandangan.
 *
 * Menggantikan donat "Submission per Status". Donat memotong satu angka
 * menjadi lima irisan tanpa urutan; dengan enam paper, membaca irisannya lebih
 * sulit daripada membaca angkanya. Yang sebenarnya ingin diketahui panitia
 * bukan komposisi status, melainkan berapa banyak yang lolos di tiap tahap dan
 * di tahap mana penyusutannya terjadi.
 *
 * Batangnya mendatar dan mengambil warna brand dari Pengaturan, sehingga ikut
 * berubah bila panitia menggantinya — bukan biru tetap yang tidak ada
 * hubungannya dengan sisa panel.
 */
class SubmissionFunnel extends ChartWidget
{
    protected ?string $heading = 'Alur Submission';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '220px';

    public static function canView(): bool
    {
        return auth()->user()?->managesSubmissions() ?? false;
    }

    public function getDescription(): ?string
    {
        $stages = $this->stages();
        $entered = $stages['Masuk'];

        if ($entered === 0) {
            return 'Belum ada abstract yang masuk pada edition ini.';
        }

        return sprintf(
            '%d dari %d abstract diterima (%d%%), %d di antaranya sudah lunas.',
            $stages['Diterima'],
            $entered,
            (int) round($stages['Diterima'] / $entered * 100),
            $stages['Lunas'],
        );
    }

    protected function getData(): array
    {
        $stages = $this->stages();

        return [
            'datasets' => [[
                'label' => 'Paper',
                'data' => array_values($stages),
                // Chart.js butuh nilai warna yang sudah jadi; ia tidak bisa
                // membaca variabel CSS panel, jadi warnanya diambil dari
                // sumber yang sama dengan warna primary panel.
                'backgroundColor' => $this->brand().'59',
                'borderColor' => $this->brand(),
                'borderWidth' => 1,
            ]],
            'labels' => array_keys($stages),
        ];
    }

    /**
     * Empat tahap yang saling bersarang: setiap tahap adalah bagian dari tahap
     * sebelumnya, sehingga batangnya selalu menyusut ke kanan dan penyusutan
     * itulah yang bisa dibaca.
     *
     * @return array<string, int>
     */
    private function stages(): array
    {
        $base = fn () => Submission::query()->ofCurrentEdition();

        return [
            'Masuk' => $base()->where('status', '!=', 'extended_abstract_draft')->count(),
            'Dinilai' => $base()->whereHas('reviewAssignments')->count(),
            'Diterima' => $base()->where('status', 'accepted')->count(),
            'Lunas' => Registration::query()
                ->where('status', 'paid')
                ->whereHas('submission', fn ($q) => $q->ofCurrentEdition())
                ->count(),
        ];
    }

    private function brand(): string
    {
        return rescue(fn () => siteSettings()->brandColor(), null, false) ?: SiteSettings::DEFAULT_BRAND;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
