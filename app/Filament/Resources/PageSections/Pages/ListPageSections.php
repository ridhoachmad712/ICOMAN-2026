<?php

namespace App\Filament\Resources\PageSections\Pages;

use App\Filament\Resources\PageSections\PageSectionResource;
use App\Models\PageSection;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPageSections extends ListRecords
{
    protected static string $resource = PageSectionResource::class;

    public function getSubheading(): ?string
    {
        return PageSection::where('target', 'home')->exists()
            ? 'Seret baris untuk mengubah urutan section di halaman. Blok yang datanya masih kosong otomatis tidak ditampilkan.'
            : 'Beranda masih memakai susunan bawaan. Muat dulu susunannya untuk bisa diatur.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('installDefaults')
                ->label('Muat Susunan Bawaan')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => ! PageSection::where('target', 'home')->exists())
                ->requiresConfirmation()
                ->modalHeading('Muat susunan beranda bawaan?')
                ->modalDescription('Susunan beranda yang tampil sekarang disalin menjadi blok-blok yang bisa Anda urutkan, ubah judulnya, sembunyikan, atau hapus. Tampilan website tidak berubah sampai Anda sendiri yang mengubahnya.')
                ->action(function (): void {
                    $created = PageSection::installDefaults('home');

                    Notification::make()
                        ->title($created.' blok dimuat.')
                        ->body('Beranda sekarang bisa Anda susun sendiri.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
