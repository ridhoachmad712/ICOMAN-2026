<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use App\Models\MenuItem;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    public function getSubheading(): ?string
    {
        return MenuItem::exists()
            ? 'Seret baris untuk mengubah urutan. Butir yang tujuannya tidak aktif otomatis disembunyikan dari website.'
            : 'Daftar ini masih kosong, jadi website memakai menu bawaan.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('installDefaults')
                ->label('Muat Menu Bawaan')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => ! MenuItem::exists())
                ->requiresConfirmation()
                ->modalHeading('Muat susunan menu bawaan?')
                ->modalDescription('Menu bawaan disalin menjadi butir-butir yang bisa Anda ubah, urutkan, dan hapus.')
                ->action(function (): void {
                    $created = MenuItem::installDefaults();

                    Notification::make()
                        ->title($created.' butir menu dimuat.')
                        ->body('Sekarang menu bisa Anda susun sendiri.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
