<?php

namespace App\Filament\Resources\PresentationRooms\RelationManagers;

use App\Filament\Resources\PresentationRooms\ModerationActions;
use App\Models\PresentationRoom;
use App\Models\RoomPresentation;
use App\Models\Submission;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Presenter di sebuah room: urutan tampil, absensi, dan nilainya. */
class PresentationsRelationManager extends RelationManager
{
    protected static string $relationship = 'presentations';

    protected static ?string $title = 'Presenter';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order', fn (): bool => ! $this->room()->isLocked())
            ->defaultSort('order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['submission.authors', 'submission.author', 'submission.topic', 'room']))
            ->columns(self::columns())
            ->headerActions([
                Action::make('addPaper')
                    ->label('Tambah paper')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => ! $this->room()->isLocked())
                    ->schema([
                        Select::make('submission_ids')
                            ->label('Paper')
                            ->helperText('Hanya paper yang berhak tampil dan belum punya room.')
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->options(fn (): array => app(RoomManager::class)
                                ->unassigned($this->room()->edition)
                                ->mapWithKeys(fn (Submission $paper) => [
                                    $paper->id => $paper->submission_number.' — '.str($paper->title)->limit(70).($paper->topic ? ' ['.$paper->topic->title.']' : ''),
                                ])
                                ->all()),
                    ])
                    ->action(function (array $data, Action $action): void {
                        foreach (Submission::whereIn('id', $data['submission_ids'])->orderBy('submission_number')->get() as $paper) {
                            ModerationActions::attempt($action, fn () => app(RoomManager::class)->assign($this->room(), $paper));
                        }

                        Notification::make()->title(count($data['submission_ids']).' paper ditambahkan')->success()->send();
                    }),
            ])
            ->recordActions([
                ModerationActions::attendance(),
                ModerationActions::score(),
                ActionGroup::make([
                    Action::make('move')
                        ->label('Pindah room')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('gray')
                        ->visible(fn (): bool => ! $this->room()->isLocked())
                        ->modalDescription(fn (RoomPresentation $record): ?string => $record->isScored()
                            ? 'Paper ini sudah dinilai. Nilai dan absensinya ikut pindah.'
                            : null)
                        ->schema([
                            Select::make('room_id')
                                ->label('Room tujuan')
                                ->required()
                                ->options(fn (): array => PresentationRoom::query()
                                    ->where('edition_id', $this->room()->edition_id)
                                    ->whereKeyNot($this->room()->id)
                                    ->whereNull('scores_submitted_at')
                                    ->orderBy('order')
                                    ->pluck('name', 'id')
                                    ->all()),
                        ])
                        ->action(function (array $data, RoomPresentation $record, Action $action): void {
                            ModerationActions::attempt($action, fn () => app(RoomManager::class)
                                ->move($record, PresentationRoom::findOrFail($data['room_id'])));

                            Notification::make()->title('Paper dipindahkan')->success()->send();
                        }),
                    DeleteAction::make()
                        ->label('Keluarkan')
                        ->visible(fn (): bool => ! $this->room()->isLocked())
                        ->modalHeading('Keluarkan dari room?')
                        ->modalDescription('Absensi dan nilai paper ini ikut terhapus. Papernya kembali ke daftar belum dapat room.'),
                ])->visible(fn (): bool => ! $this->room()->isLocked()),
            ])
            ->emptyStateHeading('Belum ada presenter')
            ->emptyStateDescription('Tambahkan paper, atau gunakan "Bagi otomatis" di daftar room.');
    }

    /** Kolom bersama untuk daftar presenter (admin_room dan moderator). */
    public static function columns(): array
    {
        return [
            TextColumn::make('order')->label('#')->width('3rem'),
            TextColumn::make('submission.submission_number')
                ->label('Paper')
                ->weight('medium')
                ->searchable()
                ->description(fn (RoomPresentation $record): string => (string) str($record->submission?->title)->limit(90))
                ->wrap(),
            TextColumn::make('writers')
                ->label('Penulis')
                ->state(fn (RoomPresentation $record): string => implode(', ', ModerationActions::writers($record)))
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
                ->default('—')
                ->description(fn (RoomPresentation $record): ?string => $record->attended() ? $record->presenter_name : null),
            TextColumn::make('paper_score')
                ->label('Nilai paper')
                ->numeric(decimalPlaces: 2)
                ->placeholder('—'),
            TextColumn::make('presenter_score')
                ->label('Nilai presenter')
                ->numeric(decimalPlaces: 2)
                ->placeholder('—'),
        ];
    }

    private function room(): PresentationRoom
    {
        /** @var PresentationRoom */
        return $this->getOwnerRecord();
    }
}
