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
        return 'Absen setiap presenter, lalu beri nilai 0–100 untuk yang hadir. Setelah semua lengkap, klik "Kirim penilaian". Nilai menjadi acuan; pemenang ditetapkan admin room.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openMeeting')
                ->label('Buka tautan room')
                ->icon('heroicon-o-video-camera')
                ->color('gray')
                ->url(fn (): ?string => $this->myRooms()->first()?->meeting_url, shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->myRooms()->count() === 1 && filled($this->myRooms()->first()->meeting_url)),

            Action::make('submitScores')
                ->label('Kirim penilaian')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalDescription('Setelah dikirim, absensi dan nilai room terkunci. Perubahan berikutnya harus lewat admin room.')
                ->schema(fn (): array => $this->openRooms()->count() > 1 ? [
                    Select::make('room_id')
                        ->label('Room')
                        ->options($this->openRooms()->pluck('name', 'id')->all())
                        ->required(),
                ] : [])
                ->visible(fn (): bool => $this->openRooms()->isNotEmpty())
                ->action(function (array $data, Action $action): void {
                    $room = $this->openRooms()->firstWhere('id', $data['room_id'] ?? $this->openRooms()->first()->id);
                    abort_unless($room, 403);

                    ModerationActions::attempt($action, fn () => app(RoomManager::class)->submitScores($room, auth()->user()));

                    Notification::make()->title('Penilaian '.$room->name.' terkirim')->success()->send();
                }),
        ];
    }

    /** @return Collection<int, PresentationRoom> */
    private function myRooms(): Collection
    {
        return PresentationRoom::query()
            ->where('edition_id', currentEdition()?->id ?? -1)
            ->where('moderator_id', auth()->id() ?? -1)
            ->orderBy('order')
            ->get();
    }

    /** @return Collection<int, PresentationRoom> */
    private function openRooms(): Collection
    {
        return $this->myRooms()->whereNull('scores_submitted_at')->values();
    }
}
