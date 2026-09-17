<?php

namespace App\Filament\Pages\Finance;

use App\Filament\Pages\Finance\Widgets\FinanceStats;
use App\Filament\Pages\Finance\Widgets\OutstandingInvoices;
use App\Filament\Pages\Finance\Widgets\RevenueByCategory;
use App\Support\FinanceSummary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Rekap keuangan konferensi.
 *
 * Tanpa halaman ini bendahara punya buku transaksi tetapi tidak punya
 * jawabannya: berapa yang sudah masuk, berapa yang masih ditunggu, dari
 * kategori mana, dan siapa yang belum melunasi. Pertanyaan-pertanyaan itu
 * selama ini dijawab dengan menyalin data ke Excel.
 *
 * Angkanya dihitung dari baris pembayaran, bukan dari status invoice.
 * Perbedaannya penting: invoice bisa saja bertanda lunas karena ditandai
 * dengan tangan, dan halaman ini tetap hanya mengakui uang yang punya
 * barisnya sendiri.
 */
class FinanceReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Submission';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Rekap Keuangan';

    protected static ?string $navigationLabel = 'Rekap Keuangan';

    public static function canAccess(): bool
    {
        return auth()->user()?->handlesMoney() ?? false;
    }

    public function getSubheading(): ?string
    {
        $edition = currentEdition();

        return $edition
            ? 'Seluruh angka dibatasi pada '.$edition->name.'.'
            : 'Belum ada edition aktif, jadi belum ada yang bisa direkap.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceStats::class,
            RevenueByCategory::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            OutstandingInvoices::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportOutstanding')
                ->label('Export Piutang')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportOutstanding()),
        ];
    }

    /**
     * Daftar piutang sebagai berkas, karena inilah yang dibawa ke rapat dan
     * dipakai menagih. Yang lunas tidak ikut: berkas ini daftar pekerjaan.
     */
    private function exportOutstanding(): StreamedResponse
    {
        $query = app(FinanceSummary::class)->stillOwing()->with(['author', 'registrationFee']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Peserta', 'Email', 'Kategori', 'Tagihan', 'Dibayar', 'Sisa', 'Cicilan', 'Tenggat pelunasan']);

            $query->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $registration) {
                    fputcsv($out, [
                        $registration->id,
                        $registration->author?->name,
                        $registration->author?->email,
                        $registration->registrationFee?->category,
                        (float) $registration->amount,
                        $registration->paidAmount(),
                        $registration->outstandingAmount(),
                        $registration->installment_plan ? 'ya' : 'tidak',
                        $registration->installmentDueAt()?->format('Y-m-d'),
                    ]);
                }
            });

            fclose($out);
        }, 'piutang-'.now()->format('Ymd-His').'.csv');
    }
}
