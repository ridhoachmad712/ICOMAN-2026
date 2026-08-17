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
        $statuses = ['submitted', 'under_review', 'revision_required', 'accepted', 'rejected'];

        $counts = Submission::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'datasets' => [[
                'label' => 'Submissions',
                'data' => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => ['#94a3b8', '#3b82f6', '#f59e0b', '#10b981', '#ef4444'],
            ]],
            'labels' => ['Submitted', 'Under Review', 'Revision', 'Accepted', 'Rejected'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
