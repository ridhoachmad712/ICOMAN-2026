<?php

namespace App\Filament\Resources\PresentationRooms\Pages;

use App\Filament\Resources\PresentationRooms\PresentationRoomResource;
use App\Models\PresentationRoom;
use App\Services\RoomManager;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPresentationRooms extends ListRecords
{
    protected static string $resource = PresentationRoomResource::class;

    public function getTitle(): string
    {
        return 'Pembagian Room';
    }

    public function getSubheading(): ?string
    {
        $edition = currentEdition();
        if (! $edition) {
            return 'Belum ada edisi aktif.';
        }

        $left = app(RoomManager::class)->unassigned($edition)->count();

        return 'Yang dibagi hanya paper yang berhak tampil: accepted, invoice lunas, dan full paper sudah masuk. '
            .($left > 0 ? $left.' paper belum dapat room.' : 'Semua paper yang berhak sudah dapat room.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('distribute')
                ->label('Bagi otomatis')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Bagi otomatis per sub-tema')
                ->modalDescription(function (): string {
                    $left = ($edition = currentEdition()) ? app(RoomManager::class)->unassigned($edition)->count() : 0;

                    return $left === 0
                        ? 'Tidak ada paper yang menunggu room.'
                        : "{$left} paper akan dimasukkan ke room dengan sub-tema yang sama (atau room campuran bila tidak ada), "
                            .'memilih room yang presenternya paling sedikit. Paper yang sudah punya room tidak dipindahkan, dan room yang terkunci dilewati.';
                })
                ->visible(fn (): bool => currentEdition() !== null)
                ->action(function (): void {
                    $result = app(RoomManager::class)->distribute(currentEdition());

                    Notification::make()
                        ->title($result['assigned'] > 0 ? $result['assigned'].' paper dibagikan ke room' : 'Tidak ada paper yang dibagikan')
                        ->body($result['left'] > 0 ? 'Buat room lebih dulu: '.$result['left'].' paper belum dapat room.' : 'Urutan tampil dapat diatur di halaman tiap room.')
                        ->color($result['left'] > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Action::make('publishAll')
                ->label('Publikasikan semua')
                ->icon('heroicon-o-megaphone')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Room, jadwal, dan urutan tampil akan terlihat di dashboard presenter.')
                ->visible(fn (): bool => currentEdition() !== null)
                ->action(function (): void {
                    $count = PresentationRoom::query()
                        ->where('edition_id', currentEdition()->id)
                        ->where('is_published', false)
                        ->update(['is_published' => true]);

                    Notification::make()->title($count.' room dipublikasikan')->success()->send();
                }),

            CreateAction::make()->label('Tambah room'),
        ];
    }
}
