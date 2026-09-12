<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Models\MenuItem;
use App\Models\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Label')
                ->description('Teks yang tampil di menu, untuk kedua bahasa.')
                ->columns(2)
                ->schema([
                    TextInput::make('label.id')->label('Indonesia')->required()->maxLength(60),
                    TextInput::make('label.en')->label('English')->required()->maxLength(60),
                ]),

            Section::make('Tujuan')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Jenis tujuan')
                        ->options([
                            'route' => 'Halaman bawaan',
                            'page' => 'Halaman buatan sendiri',
                            'url' => 'Tautan bebas',
                        ])
                        ->default('route')
                        ->required()
                        ->live(),

                    Select::make('route_name')
                        ->label('Halaman')
                        ->options(fn () => MenuItem::routeOptions())
                        ->searchable()
                        ->visible(fn ($get) => $get('type') === 'route')
                        ->helperText('Kosongkan bila butir ini hanya pembuka sub-menu.'),

                    Select::make('page_id')
                        ->label('Halaman')
                        ->options(fn () => Page::orderBy('slug')->get()->mapWithKeys(fn (Page $page) => [$page->id => $page->title.' (/p/'.$page->slug.')']))
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('type') === 'page'),

                    TextInput::make('url')
                        ->label('Alamat')
                        ->placeholder('https://...')
                        ->url()
                        ->required()
                        ->visible(fn ($get) => $get('type') === 'url'),

                    Toggle::make('opens_in_new_tab')->label('Buka di tab baru')->inline(false),
                ]),

            Section::make('Penempatan')
                ->columns(2)
                ->schema([
                    Select::make('parent_id')
                        ->label('Induk')
                        ->placeholder('Menu utama')
                        // Satu tingkat sub-menu saja: yang sudah jadi anak tidak
                        // boleh punya anak lagi.
                        ->options(fn (?MenuItem $record) => MenuItem::query()
                            ->whereNull('parent_id')
                            ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                            ->orderBy('order')
                            ->get()
                            ->mapWithKeys(fn (MenuItem $item) => [$item->id => $item->label]))
                        ->helperText('Kosongkan untuk menaruhnya di menu utama.'),

                    Toggle::make('is_published')->label('Tampilkan')->default(true)->inline(false),
                ]),
        ]);
    }
}
