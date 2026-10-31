<?php

namespace App\Filament\Resources\PresentationRooms\Pages;

use App\Filament\Resources\PresentationRooms\PresentationRoomResource;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPresentationRoom extends EditRecord
{
    protected static string $resource = PresentationRoomResource::class;

    public function getSubheading(): ?string
    {
        $room = $this->getRecord();

        return $room->isLocked()
            ? 'Penilaian sudah dikirim moderator pada '.$room->scores_submitted_at->format('d M Y H:i').'. Absensi dan nilai terkunci.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reopen')
                ->label('Buka kunci penilaian')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Moderator dapat mengubah absensi dan nilai lagi, lalu harus mengirim ulang penilaian room.')
                ->visible(fn (): bool => $this->getRecord()->isLocked())
                ->action(function (): void {
                    app(RoomManager::class)->reopen($this->getRecord());
                    Notification::make()->title('Kunci penilaian dibuka')->success()->send();
                }),
            DeleteAction::make()
                ->modalDescription('Room beserta absensi dan nilai di dalamnya dihapus. Papernya kembali ke daftar belum dapat room.'),
        ];
    }
}
