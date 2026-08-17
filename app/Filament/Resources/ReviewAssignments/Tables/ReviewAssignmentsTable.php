<?php

namespace App\Filament\Resources\ReviewAssignments\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ReviewAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('assigned_at', 'desc')
            ->columns([
                TextColumn::make('submission.submission_number')->label('No.')->searchable(),
                TextColumn::make('submission.title')->label('Title')->limit(50)->wrap()->searchable(),
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
                Action::make('downloadPaper')
                    ->label('Paper')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => $record->submission?->getFirstMediaUrl('paper') ?: null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->submission?->getFirstMediaUrl('paper')),

                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Isi Review')
                    ->schema([
                        Placeholder::make('paper_info')
                            ->hiddenLabel()
                            ->content(fn ($record): HtmlString => new HtmlString(
                                '<div class="text-sm"><strong>'.e($record->submission?->title).'</strong>'
                                .'<p class="mt-2 text-gray-500 whitespace-pre-line">'.e($record->submission?->abstract).'</p></div>'
                            )),
                        TextInput::make('score')
                            ->label('Score (1–100)')
                            ->numeric()->minValue(1)->maxValue(100),
                        Select::make('recommendation')
                            ->options([
                                'accept' => 'Accept',
                                'minor_revision' => 'Minor Revision',
                                'major_revision' => 'Major Revision',
                                'reject' => 'Reject',
                            ])
                            ->required(),
                        Textarea::make('comments_for_author')->label('Comments for Author')->rows(4),
                        Textarea::make('comments_for_committee')->label('Comments for Committee (internal)')->rows(3),
                    ])
                    ->fillForm(fn ($record) => [
                        'score' => $record->review?->score,
                        'recommendation' => $record->review?->recommendation,
                        'comments_for_author' => $record->review?->comments_for_author,
                        'comments_for_committee' => $record->review?->comments_for_committee,
                    ])
                    ->action(function (array $data, $record): void {
                        Review::updateOrCreate(
                            ['review_assignment_id' => $record->id],
                            [
                                'score' => $data['score'] ?? null,
                                'recommendation' => $data['recommendation'],
                                'comments_for_author' => $data['comments_for_author'] ?? null,
                                'comments_for_committee' => $data['comments_for_committee'] ?? null,
                                'submitted_at' => now(),
                            ],
                        );

                        $record->update(['status' => 'completed']);

                        Notification::make()->title('Review tersimpan.')->success()->send();
                    }),
            ]);
    }
}
