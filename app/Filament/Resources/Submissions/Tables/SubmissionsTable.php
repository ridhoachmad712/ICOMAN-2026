<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Models\Submission;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SubmissionsTable
{
    private const STATUS_OPTIONS = [
        'extended_abstract_draft' => 'Draft Extended Abstract',
        'extended_abstract_submitted' => 'Extended Abstract Terkirim',
        'extended_abstract_under_review' => 'Verifikasi Extended Abstract',
        'accepted' => 'Accepted',
        'rejected' => 'Tidak Lolos',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('submission_number')->label('No.')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(45)->wrap(),
                TextColumn::make('author.name')->label('Submitter')->searchable()->toggleable(),
                TextColumn::make('reviewAssignments.reviewer.name')
                    ->label('Reviewers')
                    ->badge()
                    ->separator(',')
                    ->color('info')
                    ->placeholder('Belum di-assign'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Submission::STATUS_LABELS[$state] ?? ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'extended_abstract_submitted' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('submitted_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_OPTIONS),
                SelectFilter::make('edition_id')->relationship('edition', 'name')->label('Edition'),
            ])
            ->recordActions([
                EditAction::make()->label('Detail'),

                Action::make('previewExtendedAbstract')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->visible(fn ($record) => $record->extended_abstract_draft_saved_at || $record->extended_abstract)
                    ->url(fn ($record): string => route('admin.submissions.extended-abstract.preview', $record))
                    ->openUrlInNewTab(),

                Action::make('assignReviewer')
                    ->label('Assign Reviewer')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn ($record) => $record->currentReviewPhase() !== null
                        && ! $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'completed')
                            ->exists())
                    ->schema([
                        Select::make('reviewer_ids')
                            ->label('Pilih Reviewer')
                            ->multiple()
                            ->options(fn () => User::role('reviewer')->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->helperText('Pilih 1–2 dosen internal dengan role reviewer.'),
                    ])
                    ->fillForm(fn ($record) => [
                        'reviewer_ids' => $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->pluck('reviewer_id')
                            ->toArray(),
                    ])
                    ->action(function (array $data, $record): void {
                        $phase = $record->currentReviewPhase();
                        if (! $phase) {
                            return;
                        }

                        $selected = $data['reviewer_ids'] ?? [];
                        $phaseAssignments = $record->reviewAssignments()->where('phase', $phase);
                        $existing = (clone $phaseAssignments)->pluck('reviewer_id')->toArray();

                        // Hapus reviewer yang tidak lagi dipilih
                        $toRemove = array_diff($existing, $selected);
                        if (! empty($toRemove)) {
                            (clone $phaseAssignments)->whereIn('reviewer_id', $toRemove)->delete();
                        }

                        // Tambahkan reviewer baru
                        foreach ($selected as $rid) {
                            $record->reviewAssignments()->firstOrCreate(
                                ['reviewer_id' => $rid, 'phase' => $phase],
                                ['assigned_at' => now(), 'status' => 'pending'],
                            );
                        }

                        $record->changeStatus(count($selected) > 0 ? 'extended_abstract_under_review' : 'extended_abstract_submitted');

                        Notification::make()->title('Reviewer berhasil ditugaskan & status diperbarui.')->success()->send();
                    }),

                Action::make('decision')
                    ->label('Keputusan Review')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->modalHeading('Tentukan Hasil Verifikasi Extended Abstract')
                    ->visible(fn ($record) => $record->currentReviewPhase() !== null
                        && $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'completed')
                            ->exists()
                        && ! $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'pending')
                            ->exists())
                    ->schema([
                        Placeholder::make('review_summary')
                            ->label('Hasil Penilaian Reviewer')
                            ->content(function ($record): HtmlString {
                                $completed = $record->reviewAssignments()
                                    ->where('phase', $record->currentReviewPhase())
                                    ->where('status', 'completed')
                                    ->with(['reviewer', 'review'])
                                    ->get();
                                if ($completed->isEmpty()) {
                                    return new HtmlString('<p class="text-xs text-gray-500">Belum ada review selesai.</p>');
                                }

                                $html = $completed->map(function ($ra) {
                                    $rev = $ra->review;
                                    $rec = $rev?->recommendation ? ucwords(str_replace('_', ' ', $rev->recommendation)) : '—';
                                    $score = $rev?->score ? $rev->score.'/100' : '—';
                                    $comments = $rev?->comments_for_author ? '<p class="mt-1 text-xs text-gray-600"><strong>Catatan Reviewer:</strong> '.e($rev->comments_for_author).'</p>' : '';

                                    return '<div class="p-3 bg-gray-50 border border-gray-200 rounded-lg mb-2 text-xs">'
                                        .'<div class="font-bold text-gray-900">'.e($ra->reviewer?->name).'</div>'
                                        .'<div>Skor: <span class="font-semibold">'.$score.'</span> &bull; Rekomendasi: <span class="font-semibold text-primary-700">'.$rec.'</span></div>'
                                        .$comments
                                        .'</div>';
                                })->implode('');

                                return new HtmlString($html);
                            }),

                        Select::make('status')
                            ->label('Keputusan Panitia')
                            ->options([
                                'accepted' => 'Accepted (Terbitkan LoA)',
                                'rejected' => 'Tidak Lolos',
                            ])
                            ->required()
                            ->helperText('Author akan otomatis menerima email notifikasi resmi sesuai keputusan ini.'),
                    ])
                    ->action(function (array $data, $record): void {
                        $record->changeStatus($data['status']);

                        Notification::make()->title('Keputusan berhasil disimpan dan notifikasi dikirim ke author.')->success()->send();
                    }),

                Action::make('changeStatus')
                    ->label('Ubah Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn ($record) => ! ($record->currentReviewPhase() !== null
                        && $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'completed')
                            ->exists()
                        && ! $record->reviewAssignments()
                            ->where('phase', $record->currentReviewPhase())
                            ->where('status', 'pending')
                            ->exists()))
                    ->schema([
                        Select::make('status')->options(self::STATUS_OPTIONS)->required(),
                    ])
                    ->fillForm(fn ($record) => ['status' => $record->status])
                    ->action(function (array $data, $record): void {
                        $record->changeStatus($data['status']);

                        Notification::make()->title('Status diperbarui, email dikirim ke author.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
