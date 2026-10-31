<?php

namespace App\Filament\Resources\PresentationResults\Tables;

use App\Filament\Resources\PresentationRooms\ModerationActions;
use App\Models\PresentationRoom;
use App\Models\RoomPresentation;
use App\Services\PresentationResultExport;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PresentationResultsTable
{
    public static function configure(Table $table): Table
    {
        $ranks = collect(range(1, RoomPresentation::WINNERS))->mapWithKeys(fn (int $rank) => [$rank => 'Juara '.$rank])->all();

        $rankColumn = fn (string $column, string $label) => TextColumn::make($column)
            ->label($label)
            ->badge()
            ->formatStateUsing(fn (?int $state): string => $state ? 'Juara '.$state : '')
            ->color('success')
            ->placeholder('—')
            ->sortable();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['submission.authors', 'submission.author', 'room']))
            ->columns([
                TextColumn::make('room.name')
                    ->label('Room')
                    ->sortable(),
                TextColumn::make('submission.submission_number')
                    ->label('Paper')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (RoomPresentation $record): string => (string) str($record->submission?->title)->limit(80))
                    ->wrap(),
                TextColumn::make('presenter_name')
                    ->label('Presenter')
                    ->state(fn (RoomPresentation $record): string => $record->presenter_name ?: implode(', ', ModerationActions::writers($record)))
                    ->description(fn (RoomPresentation $record): ?string => $record->submission?->author?->affiliation)
                    ->wrap(),
                TextColumn::make('attendance')
                    ->label('Kehadiran')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RoomPresentation::ATTENDANCE[$state] ?? 'Belum diabsen')
                    ->color(fn (?string $state): string => match ($state) {
                        'present' => 'success',
                        'represented' => 'info',
                        'absent' => 'danger',
                        default => 'gray',
                    })
                    ->default('—'),
                TextColumn::make('paper_score')
                    ->label('Nilai paper')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('presenter_score')
                    ->label('Nilai presenter')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->sortable(),
                $rankColumn('best_paper_rank', 'Best Paper'),
                $rankColumn('best_presenter_rank', 'Best Presenter'),
                TextColumn::make('moderator_notes')
                    ->label('Catatan moderator')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('presentation_room_id')
                    ->label('Room')
                    ->options(fn (): array => PresentationRoom::query()
                        ->where('edition_id', currentEdition()?->id ?? -1)
                        ->orderBy('order')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('attendance')
                    ->label('Kehadiran')
                    ->options(RoomPresentation::ATTENDANCE),
            ])
            ->recordActions([
                Action::make('award')
                    ->label('Tetapkan pemenang')
                    ->icon('heroicon-o-trophy')
                    ->color('warning')
                    ->visible(fn (RoomPresentation $record): bool => $record->attended())
                    ->modalHeading(fn (RoomPresentation $record): string => 'Pemenang: '.$record->submission?->submission_number)
                    ->modalDescription(fn (RoomPresentation $record): string => sprintf(
                        'Nilai paper %s · nilai presenter %s. Peringkat yang sudah dipegang paper lain akan dipindahkan ke paper ini.',
                        $record->paper_score ?? '—',
                        $record->presenter_score ?? '—',
                    ))
                    ->modalWidth('lg')
                    ->fillForm(fn (RoomPresentation $record): array => [
                        'best_paper' => $record->best_paper_rank,
                        'best_presenter' => $record->best_presenter_rank,
                    ])
                    ->schema([
                        Select::make('best_paper')->label('Best Paper')->options($ranks)->placeholder('Bukan pemenang'),
                        Select::make('best_presenter')->label('Best Presenter')->options($ranks)->placeholder('Bukan pemenang'),
                    ])
                    ->action(function (array $data, RoomPresentation $record, Action $action): void {
                        $manager = app(RoomManager::class);

                        ModerationActions::attempt($action, function () use ($manager, $record, $data): void {
                            $manager->setRank($record, 'best_paper', filled($data['best_paper']) ? (int) $data['best_paper'] : null);
                            $manager->setRank($record->refresh(), 'best_presenter', filled($data['best_presenter']) ? (int) $data['best_presenter'] : null);
                        });

                        Notification::make()->title('Pemenang tersimpan')->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Unduh rekap (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->tooltip('Mengikuti tab, filter, dan pencarian yang sedang aktif.')
                    ->action(function (HasTable $livewire, PresentationResultExport $export) {
                        return response()
                            ->download($export->create($livewire->getFilteredSortedTableQuery()), $export->fileName())
                            ->deleteFileAfterSend();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-trophy')
            ->emptyStateHeading('Belum ada presentasi')
            ->emptyStateDescription('Rekap muncul setelah paper dibagikan ke room.');
    }
}
