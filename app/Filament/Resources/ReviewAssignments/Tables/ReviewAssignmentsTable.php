<?php

namespace App\Filament\Resources\ReviewAssignments\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextColumn::make('submission.submission_number')->label('No. Paper')->searchable()->sortable(),
                TextColumn::make('submission.title')->label('Title')->limit(50)->wrap()->searchable(),
                TextColumn::make('phase')
                    ->label('Tahap')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'abstract' ? 'Review Abstrak' : 'Verifikasi Extended Abstract')
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
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Verifikasi Extended Abstract')
                    ->visible(fn ($record) => auth()->user()?->id === $record->reviewer_id || (auth()->user()?->isSuperadmin() ?? false))
                    ->schema([
                        Placeholder::make('paper_info')
                            ->hiddenLabel()
                            ->content(function ($record): HtmlString {
                                $submission = $record->submission;
                                if ($record->phase === 'extended_abstract') {
                                    $pdfUrl = route('admin.submissions.extended-abstract.preview', $submission);
                                    $document = view('components.extended-abstract-document', ['submission' => $submission])->render();

                                    return new HtmlString(
                                        '<div class="mb-4"><strong>'.e($submission?->title).'</strong></div>'
                                        .'<a class="mb-5 inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold" href="'.e($pdfUrl).'" target="_blank">Buka Preview PDF</a>'
                                        .$document,
                                    );
                                }

                                $content = $submission?->abstract;
                                $keywords = $record->phase === 'abstract' && filled($submission?->keywords)
                                    ? '<p class="mt-3 text-xs text-gray-600"><strong>Keywords:</strong> '.e(implode(', ', $submission->keywords)).'</p>'
                                    : '';

                                return new HtmlString(
                                    '<div class="text-sm"><strong>'.e($submission?->title).'</strong>'
                                    .'<p class="mt-2 text-gray-500 whitespace-pre-line">'.e($content).'</p>'
                                    .$keywords.'</div>'
                                );
                            }),
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
                        Toggle::make('recommends_sinta3')
                            ->label('Direkomendasikan untuk Jurnal SINTA 3')
                            ->helperText('Bila diaktifkan & naskah diterima, penulis ditawari opsi penerbitan SINTA 3 (biaya tambahan) saat pembayaran.')
                            ->visible(fn ($record) => $record->phase === 'extended_abstract')
                            ->default(false),
                        Textarea::make('comments_for_author')->label('Comments for Author')->rows(4),
                        Textarea::make('comments_for_committee')->label('Comments for Committee (internal)')->rows(3),
                    ])
                    ->fillForm(fn ($record) => [
                        'score' => $record->review?->score,
                        'recommendation' => $record->review?->recommendation,
                        'recommends_sinta3' => (bool) $record->review?->recommends_sinta3,
                        'comments_for_author' => $record->review?->comments_for_author,
                        'comments_for_committee' => $record->review?->comments_for_committee,
                    ])
                    ->action(function (array $data, $record): void {
                        Review::updateOrCreate(
                            ['review_assignment_id' => $record->id],
                            [
                                'score' => $data['score'] ?? null,
                                'recommendation' => $data['recommendation'],
                                'recommends_sinta3' => (bool) ($data['recommends_sinta3'] ?? false),
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
