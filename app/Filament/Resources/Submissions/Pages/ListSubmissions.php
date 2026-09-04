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

    /**
     * Tab mengikuti ALUR KERJA, bukan daftar status mentah, dan dibuat tidak
     * saling tumpang tindih supaya satu paper hanya "menunggu" di satu antrean.
     * Status lain (draft, perlu revisi) tetap dapat dicari lewat filter Status.
     */
    public function getTabs(): array
    {
        return [
            'action' => Tab::make('Perlu Tindakan')
                ->badge(self::needsActionQuery(Submission::query())->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => self::needsActionQuery($query)),

            'under_review' => Tab::make('Sedang Direview')
                ->badge(Submission::whereHas('reviewAssignments', fn ($q) => $q->where('status', 'pending'))->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn ($query) => $query->whereHas('reviewAssignments', fn ($q) => $q->where('status', 'pending'))),

            'accepted' => Tab::make('Accepted')
                ->badge(Submission::where('status', 'accepted')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'accepted')),

            'rejected' => Tab::make('Ditolak')
                ->badge(Submission::where('status', 'rejected')->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),

            'all' => Tab::make('Semua')
                ->badge(Submission::count())
                ->badgeColor('gray'),
        ];
    }

    /** Paper yang bolanya ada di panitia: belum di-assign, menunggu keputusan, atau LOA belum terbit. */
    private static function needsActionQuery($query)
    {
        return $query->where(function ($outer) {
            $outer
                // Sudah dikirim author, reviewer belum ditugaskan.
                ->where(fn ($q) => $q->where('status', 'extended_abstract_submitted')
                    ->whereDoesntHave('reviewAssignments', fn ($ra) => $ra->where('phase', 'extended_abstract')))
                // Semua reviewer selesai menilai, menunggu keputusan panitia.
                ->orWhere(fn ($q) => $q->whereIn('status', ['extended_abstract_submitted', 'extended_abstract_under_review'])
                    ->whereHas('reviewAssignments', fn ($ra) => $ra->where('status', 'completed'))
                    ->whereDoesntHave('reviewAssignments', fn ($ra) => $ra->where('status', 'pending')))
                // Sudah accepted tetapi LOA belum terbit (mis. data lama).
                ->orWhere(fn ($q) => $q->where('status', 'accepted')->whereNull('loa_issued_at'));
        });
    }
}
