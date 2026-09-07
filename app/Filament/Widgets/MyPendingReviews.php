<?php

namespace App\Filament\Widgets;

use App\Models\ReviewAssignment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Reviewer sebelumnya mendapat dashboard kosong. Widget ini memberi satu hal
 * yang memang ia butuhkan: berapa review yang menunggu dirinya.
 */
class MyPendingReviews extends BaseWidget
{
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('reviewer'));
    }

    protected function getStats(): array
    {
        $mine = ReviewAssignment::where('reviewer_id', auth()->id());

        $pending = (clone $mine)->where('status', 'pending')->count();
        $done = (clone $mine)->where('status', 'completed')->count();

        return [
            Stat::make('Menunggu review Anda', $pending)
                ->description($pending > 0 ? 'Perlu dinilai' : 'Tidak ada yang tertunda')
                ->color($pending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('Sudah Anda nilai', $done)
                ->description('Total sepanjang konferensi')
                ->color('gray')
                ->icon('heroicon-o-check-circle'),
        ];
    }
}
