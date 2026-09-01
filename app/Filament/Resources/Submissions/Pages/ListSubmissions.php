<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['No', 'Title', 'Submitter', 'Email', 'Topic', 'Status', 'Submitted At']);

                    Submission::with(['author', 'topic'])->orderBy('id')->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $s) {
                            fputcsv($out, [
                                $s->submission_number,
                                $s->title,
                                $s->author?->name,
                                $s->author?->email,
                                $s->topic?->title,
                                $s->status,
                                $s->submitted_at?->format('Y-m-d H:i'),
                            ]);
                        }
                    });

                    fclose($out);
                }, 'submissions-'.now()->format('Ymd-His').'.csv')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Submission')
                ->badge(Submission::count())
                ->badgeColor('gray'),

            'unassigned' => Tab::make('Belum Ditugaskan')
                ->badge(Submission::where('status', 'extended_abstract_submitted')->whereDoesntHave('reviewAssignments', fn ($query) => $query->where('phase', 'extended_abstract'))->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'extended_abstract_submitted')->whereDoesntHave('reviewAssignments', fn ($reviewQuery) => $reviewQuery->where('phase', 'extended_abstract'))),

            'draft' => Tab::make('Draft Author')
                ->badge(Submission::where('status', 'extended_abstract_draft')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'extended_abstract_draft')),

            'under_review' => Tab::make('Sedang Direview')
                ->badge(Submission::whereHas('reviewAssignments', fn ($q) => $q->where('status', 'pending'))->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->whereHas('reviewAssignments', fn ($q) => $q->where('status', 'pending'))),

            'review_completed' => Tab::make('Review Selesai')
                ->badge(Submission::whereHas('reviewAssignments', fn ($q) => $q->where('status', 'completed'))->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn ($query) => $query->whereHas('reviewAssignments', fn ($q) => $q->where('status', 'completed'))),

            'extended' => Tab::make('Verifikasi Extended Abstract')
                ->badge(Submission::whereIn('status', ['extended_abstract_submitted', 'extended_abstract_under_review'])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['extended_abstract_submitted', 'extended_abstract_under_review'])),

            'accepted' => Tab::make('Accepted (LoA)')
                ->badge(Submission::where('status', 'accepted')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'accepted')),

            'rejected' => Tab::make('Ditolak')
                ->badge(Submission::where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),
        ];
    }
}
