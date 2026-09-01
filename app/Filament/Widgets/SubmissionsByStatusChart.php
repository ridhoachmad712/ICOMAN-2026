<?php

namespace App\Filament\Widgets;

use App\Models\Submission;
use Filament\Widgets\ChartWidget;

class SubmissionsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Submission per Status';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }

    protected function getData(): array
    {
        $statuses = [
            'extended_abstract_draft',
            'extended_abstract_submitted',
            'extended_abstract_under_review',
            'accepted',
            'rejected',
        ];

        $counts = Submission::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'datasets' => [[
                'label' => 'Submissions',
                'data' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => ['#94a3b8', '#f59e0b', '#8b5cf6', '#16a34a', '#ef4444'],
            ]],
            'labels' => ['Draft Extended Abstract', 'Extended Terkirim', 'Verifikasi Extended', 'Accepted', 'Tidak Lolos'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
