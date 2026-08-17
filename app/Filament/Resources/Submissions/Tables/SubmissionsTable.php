<?php

namespace App\Filament\Resources\Submissions\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubmissionsTable
{
    private const STATUS_OPTIONS = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'revision_required' => 'Revision Required',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('submission_number')->label('No.')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(45)->wrap(),
                TextColumn::make('author.name')->label('Submitter')->searchable()->toggleable(),
                TextColumn::make('reviewAssignments_count')->counts('reviewAssignments')->label('Reviewers')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'revision_required' => 'warning',
                        'under_review' => 'info',
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

                Action::make('assignReviewer')
                    ->label('Assign Reviewer')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->schema([
                        Select::make('reviewer_ids')
                            ->label('Reviewers')
                            ->multiple()
                            ->options(fn () => User::role('reviewer')->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->helperText('Pilih 1–2 reviewer (dosen internal dengan role reviewer).'),
                    ])
                    ->action(function (array $data, $record): void {
                        foreach ($data['reviewer_ids'] as $rid) {
                            $record->reviewAssignments()->firstOrCreate(
                                ['reviewer_id' => $rid],
                                ['assigned_at' => now(), 'status' => 'pending'],
                            );
                        }

                        if ($record->status === 'submitted') {
                            $record->changeStatus('under_review');
                        }

                        Notification::make()->title('Reviewer ditugaskan.')->success()->send();
                    }),

                Action::make('changeStatus')
                    ->label('Ubah Status')
                    ->icon('heroicon-o-arrow-path')
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
