<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RegistrationStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
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
