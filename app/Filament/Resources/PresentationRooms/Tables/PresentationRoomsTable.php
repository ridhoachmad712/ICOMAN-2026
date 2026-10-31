<?php

namespace App\Filament\Resources\PresentationRooms\Tables;

use App\Models\PresentationRoom;
use App\Models\RoomPresentation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PresentationRoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('order')
            ->defaultSort('order')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['topic', 'moderator'])
                ->withCount([
                    'presentations',
                    'presentations as attended_count' => fn (Builder $query) => $query->whereIn('attendance', RoomPresentation::ATTENDED),
                    'presentations as scored_count' => fn (Builder $query) => $query->whereNotNull('scored_at'),
                ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Room')
                    ->weight('medium')
                    ->searchable()
                    ->description(fn (PresentationRoom $record): ?string => $record->topic?->title),
                TextColumn::make('starts_at')
                    ->label('Jadwal')
                    ->state(fn (PresentationRoom $record): ?string => $record->scheduleLabel())
                    ->placeholder('Belum dijadwalkan')
                    ->description(fn (PresentationRoom $record): ?string => $record->meeting_note),
                TextColumn::make('moderator.name')
                    ->label('Moderator')
                    ->placeholder('Belum ditunjuk')
                    ->color(fn (PresentationRoom $record): ?string => $record->moderator ? null : 'danger'),
                TextColumn::make('presentations_count')
                    ->label('Presenter')
                    ->description(fn (PresentationRoom $record): string => "{$record->attended_count} hadir · {$record->scored_count} dinilai"),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (PresentationRoom $record): string => match (true) {
                        $record->isLocked() => 'Penilaian terkirim',
                        $record->is_published => 'Dipublikasikan',
                        default => 'Draft',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Penilaian terkirim' => 'success',
                        'Dipublikasikan' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Kelola'),
                DeleteAction::make()
                    ->modalDescription('Room beserta absensi dan nilai di dalamnya dihapus. Papernya kembali ke daftar belum dapat room.'),
            ])
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('Belum ada room')
            ->emptyStateDescription('Buat room terlebih dulu, lalu gunakan "Bagi otomatis" atau tambahkan paper satu per satu.');
    }
}
