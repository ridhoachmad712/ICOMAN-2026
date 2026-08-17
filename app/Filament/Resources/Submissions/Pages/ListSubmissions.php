<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

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
}
