<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Sisi uang: registrasi dan pembayaran.
 *
 * Diberi judul dan diletakkan di bawah papan kerja supaya dashboard terbaca
 * sebagai dua hal yang memang berbeda — pekerjaan atas paper di atas, uang di
 * bawah — bukan enam angka campur aduk dalam satu baris.
 */
class RegistrationStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Registrasi & Pembayaran';

    protected ?string $description = 'Angka dari seluruh registrasi yang tercatat.';

    public static function canView(): bool
    {
        return auth()->user()?->handlesMoney() ?? false;
    }

    protected function getStats(): array
    {
        $total = Registration::count();
        $paid = Registration::where('status', 'paid')->count();
        $pending = Registration::where('status', 'pending_verification')->count();
        $revenue = (float) Registration::where('status', 'paid')->sum('amount');

        return [
            Stat::make('Total Registrasi', (string) $total)
                ->description($paid.' lunas')
                ->color('primary'),

            Stat::make('Menunggu Verifikasi', (string) $pending)
                ->description('Transfer manual')
                ->color($pending > 0 ? 'warning' : 'gray'),

            Stat::make('Estimasi Pemasukan', 'IDR '.number_format($revenue, 0, ',', '.'))
                ->description('Dari registrasi lunas')
                ->color('success'),
        ];
    }
}
