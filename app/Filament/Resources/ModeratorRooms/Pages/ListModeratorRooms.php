<?php

namespace App\Filament\Resources\ModeratorRooms\Pages;

use App\Filament\Resources\ModeratorRooms\ModeratorRoomResource;
use App\Filament\Resources\PresentationRooms\ModerationActions;
use App\Models\PresentationRoom;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

class ListModeratorRooms extends ListRecords
{
    protected static string $resource = ModeratorRoomResource::class;

    public function getTitle(): string
    {
        return 'Room Saya';
    }

    public function getSubheading(): ?string
    {
        return 'Absen setiap presenter, beri nilai yang hadir, lalu kirim penilaian.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.moderator.room-summary'),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitScores')
                ->label('Kirim penilaian')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalDescription('Setelah dikirim, absensi dan nilai room terkunci. Perubahan berikutnya harus lewat admin room.')
                ->schema(fn (): array => $this->openRooms()->count() > 1 ? [
                    Select::make('room_id')
                        ->label('Room')
                        ->options($this->readyRooms()->pluck('name', 'id')->all())
                        ->required(),
                ] : [])
                ->visible(fn (): bool => $this->openRooms()->isNotEmpty())
                ->disabled(fn (): bool => $this->readyRooms()->isEmpty())
                ->tooltip(fn (): ?string => $this->readyRooms()->isEmpty()
                    ? 'Belum bisa dikirim: '.$this->openRooms()->map(fn (PresentationRoom $room): string => $room->name.': '.$room->scoringGap())->implode(' ')
                    : null)
                ->action(function (array $data, Action $action): void {
                    $room = $this->readyRooms()->firstWhere('id', $data['room_id'] ?? $this->readyRooms()->first()?->id);
                    abort_unless($room, 403);

                    ModerationActions::attempt($action, fn () => app(RoomManager::class)->submitScores($room, auth()->user()));

                    Notification::make()->title('Penilaian '.$room->name.' terkirim')->success()->send();
                }),
        ];
    }

    /** @return Collection<int, PresentationRoom> */
    private function openRooms(): Collection
    {
        return ModeratorRoomResource::myRooms()->whereNull('scores_submitted_at')->values();
    }

    /** @return Collection<int, PresentationRoom> */
    private function readyRooms(): Collection
    {
        return $this->openRooms()->filter(fn (PresentationRoom $room): bool => $room->scoringProgress()['ready'])->values();
    }
}
