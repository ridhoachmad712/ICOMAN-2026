<?php

namespace App\Filament\Pages\Finance\Widgets;

use App\Support\FinanceSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Empat angka teratas rekap keuangan.
 *
 * Widget ini sengaja TIDAK berada di app/Filament/Widgets: panel hanya
 * menemukan widget dashboard dari sana, dan angka-angka ini milik halaman
 * rekap, bukan tempelan tambahan di dashboard.
 */
class FinanceStats extends StatsOverviewWidget
{
    // Dimuat bersama halamannya, bukan lewat permintaan terpisah: tiga bagian
    // rekap yang memuat dirinya sendiri membuat halaman berkedip "Loading..."
    // sebentar, dan pada server yang melayani satu permintaan pada satu waktu
    // ketiganya bisa saling menunggu. Kueri di sini ringan.
    protected static bool $isLazy = false;

    protected ?string $heading = 'Ringkasan';

    protected ?string $description = 'Seluruh angka dihitung dari baris pembayaran yang berhasil, bukan dari status invoice.';

    protected function getStats(): array
    {
        $summary = app(FinanceSummary::class);

        $received = $summary->received();
        $outstanding = $summary->outstanding();
        $waived = $summary->waived();
        $overdue = $summary->overdueInstallments();
        $awaiting = $summary->awaitingVerification();

        return [
            Stat::make('Uang Masuk', rupiah($received))
                ->description('Dari pembayaran yang berhasil')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Piutang', rupiah($outstanding))
                ->description($overdue > 0
                    ? $overdue.' cicilan lewat tenggat pelunasan'
                    : 'Tidak ada cicilan yang menunggak')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdue > 0 ? 'danger' : 'warning'),

            Stat::make('Dibebaskan Voucher', rupiah($waived))
                ->description('Terdaftar tanpa uang masuk')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('gray'),

            Stat::make('Menunggu Verifikasi', (string) $awaiting)
                ->description($awaiting > 0 ? 'Perlu diperiksa panitia' : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($awaiting > 0 ? 'warning' : 'gray'),
        ];
    }
}
