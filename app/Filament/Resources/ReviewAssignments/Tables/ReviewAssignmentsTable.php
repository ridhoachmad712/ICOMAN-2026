<?php

namespace App\Filament\Resources\ReviewAssignments\Tables;

use App\Filament\Resources\ReviewAssignments\ReviewAssignmentResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('submission.submission_number')->label('No. Paper')->searchable()->sortable(),
                TextColumn::make('submission.title')->label('Title')->limit(50)->wrap()->searchable(),
                TextColumn::make('phase')
                    ->label('Tahap')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'abstract' ? 'Review Abstrak' : 'Review Abstract')
                    ->color(fn (string $state) => $state === 'abstract' ? 'info' : 'primary'),
                TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi']) ?? false),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'completed' ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('review.recommendation')
                    ->label('Recommendation')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                TextColumn::make('review.score')->label('Score')->placeholder('—'),
                TextColumn::make('assigned_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'completed' => 'Completed']),
            ])
            ->recordActions([
                // Penilaian pindah ke halaman tersendiri: naskah dan formulir
                // berdampingan, tidak lagi berdesakan dalam satu modal.
                Action::make('assess')
                    ->label(fn ($record) => $record->status === 'completed' ? __('review.continue') : __('review.open'))
                    ->icon('heroicon-o-pencil-square')
                    ->color(fn ($record) => $record->status === 'completed' ? 'gray' : 'primary')
                    ->url(fn ($record) => ReviewAssignmentResource::getUrl('assess', ['record' => $record]))
                    ->visible(fn ($record) => auth()->user()?->id === $record->reviewer_id || (auth()->user()?->isSuperadmin() ?? false)),
            ]);
    }
}
