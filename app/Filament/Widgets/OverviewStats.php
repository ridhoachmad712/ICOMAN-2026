<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Speaker;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }

    protected function getStats(): array
    {
        $edition = currentEdition();

        $speakerCount = $edition
            ? Speaker::where('edition_id', $edition->id)->count()
            : 0;

        $unread = ContactMessage::where('is_read', false)->count();

        $daysLeft = $this->daysLeftLabel($edition?->start_date);

        return [
            Stat::make('Speaker', (string) $speakerCount)
                ->description($edition?->name ?? 'Belum ada edition aktif')
                ->color('primary'),

            Stat::make('Hari menuju Conference', $daysLeft)
                ->description($edition?->start_date ? 'Mulai '.$edition->start_date->format('d M Y') : 'Tanggal belum diatur')
                ->color('success'),

            Stat::make('Pesan belum dibaca', (string) $unread)
                ->description('Contact messages')
                ->color($unread > 0 ? 'warning' : 'gray'),
        ];
    }

    private function daysLeftLabel($startDate): string
    {
        if (! $startDate) {
            return '—';
        }

        $today = CarbonImmutable::today();
        $start = CarbonImmutable::parse($startDate)->startOfDay();

        if ($start->isPast() && ! $start->isToday()) {
            return 'Selesai';
        }

        $days = $today->diffInDays($start, false);

        return $days <= 0 ? 'Hari ini' : (string) (int) $days.' hari';
    }
}
