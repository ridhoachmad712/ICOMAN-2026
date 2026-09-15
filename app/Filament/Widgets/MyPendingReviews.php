<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use App\Models\ReviewAssignment;
use App\Services\ConferenceDeadlines;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Meja kerja reviewer.
 *
 * Dashboard sebelumnya hanya dua angka yang tidak bisa diklik: reviewer tahu
 * ada satu paper menunggu, lalu harus mencari sendiri menunya. Sekarang yang
 * tampil adalah pekerjaannya sendiri, lengkap dengan tombol menilai.
 */
class MyPendingReviews extends BaseWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) (auth()->user()?->hasRole('reviewer'));
    }

    /** Ringkasan singkat: berapa yang sudah dinilai dan kapan batasnya. */
    private function summary(): string
    {
        $deadline = app(ConferenceDeadlines::class)->date('acceptance');
        $done = ReviewAssignment::where('reviewer_id', auth()->id())->where('status', 'completed')->count();

        $parts = [__('review.desk_done').': '.$done];

        if ($deadline) {
            $parts[] = __('review.desk_deadline').': '.$deadline->translatedFormat('d M Y')
                .' ('.($deadline->isPast()
                    ? __('review.desk_overdue')
                    // Carbon 3 mengembalikan pecahan; sisa hari dibulatkan ke bawah.
                    : (int) now()->diffInDays($deadline).' '.__('review.desk_days_left')).')';
        } else {
            $parts[] = __('review.desk_deadline').': '.__('review.desk_deadline_unset');
        }

        return implode(' · ', $parts);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('review.desk_title'))
            ->description($this->summary())
            ->query(
                ReviewAssignment::query()
                    ->with(['submission.topic'])
                    ->where('reviewer_id', auth()->id())
                    // Yang sudah dinilai tidak lagi menuntut perhatian; tetap
                    // bisa dibuka lewat menu My Reviews.
                    ->where('status', 'pending')
            )
            ->defaultSort('assigned_at')
            ->paginated(false)
            ->columns([
                TextColumn::make('submission.submission_number')->label('No. Paper'),
                TextColumn::make('submission.title')->label('Judul')->limit(60)->wrap(),
                TextColumn::make('submission.topic.title')->label('Topik')->limit(32)->placeholder('—')->toggleable(),
                TextColumn::make('phase')
                    ->label('Tahap')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'abstract' ? 'Review Abstrak' : 'Review Abstract')
                    ->color('info'),
                TextColumn::make('assigned_at')->label('Ditugaskan')->date('d M Y')->sortable(),
            ])
            ->recordActions([
                Action::make('assess')
                    ->label(__('review.open'))
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->url(fn (ReviewAssignment $record) => ReviewAssignmentResource::getUrl('assess', ['record' => $record])),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading(__('review.desk_empty_title'))
            ->emptyStateDescription(__('review.desk_empty_body'));
    }

    protected function applySearchToTableQuery(Builder $query): Builder
    {
        return $query;
    }
}
