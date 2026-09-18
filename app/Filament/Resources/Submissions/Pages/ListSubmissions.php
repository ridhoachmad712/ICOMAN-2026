<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\Submission;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;

class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn ($query) => $query->ofCurrentEdition());
    }

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

                    Submission::ofCurrentEdition()->with(['author', 'topic'])->orderBy('id')->chunk(200, function ($rows) use ($out) {
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

    /**
     * Tab mengikuti ALUR KERJA, bukan daftar status mentah, dan dibuat tidak
     * saling tumpang tindih supaya satu paper hanya "menunggu" di satu antrean.
     * Status lain (draft, perlu revisi) tetap dapat dicari lewat filter Status.
     *
     * Definisi antreannya milik model (`Submission::scopeNeedsAction()` dan
     * kawan-kawannya), yang juga dipakai papan kerja di dashboard — supaya
     * badge di sini dan angka di sana tidak pernah berbeda.
     */
    public function getTabs(): array
    {
        return [
            'action' => Tab::make('Perlu Tindakan')
                ->badge(Submission::ofCurrentEdition()->needsAction()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->needsAction()),

            'under_review' => Tab::make('Sedang Direview')
                ->badge(Submission::ofCurrentEdition()->underReview()->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->underReview()),

            'accepted' => Tab::make('Accepted')
                ->badge(Submission::ofCurrentEdition()->where('status', 'accepted')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'accepted')),

            'rejected' => Tab::make('Ditolak')
                ->badge(Submission::ofCurrentEdition()->where('status', 'rejected')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),

            'all' => Tab::make('Semua')
                ->badge(Submission::ofCurrentEdition()->count())
                ->badgeColor('gray'),
        ];
    }
}
