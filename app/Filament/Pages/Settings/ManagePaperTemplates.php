<?php

namespace App\Filament\Pages\Settings;

use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManagePaperTemplates extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Template Paper';

    protected static ?string $navigationLabel = 'Template Paper';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'admin_registrasi', 'content_admin']) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Link template naskah final')
                ->description('Isi URL unduhan untuk setiap jalur jurnal. Author hanya melihat satu link yang sesuai dengan target jurnal papernya. Kosongkan link untuk menyembunyikan tombol unduhan pada jalur tersebut.')
                ->schema([
                    TextInput::make('paper_template_regular_url')
                        ->label('Template Jurnal Reguler')
                        ->placeholder('https://...')
                        ->helperText('Link publik yang dapat dibuka author tanpa akun admin.')
                        ->url()
                        ->rules(['nullable', 'url:http,https', 'max:2048'])
                        ->maxLength(2048),
                    TextInput::make('paper_template_sinta3_url')
                        ->label('Template Jurnal SINTA 3')
                        ->placeholder('https://...')
                        ->helperText('Link publik yang dapat dibuka author tanpa akun admin.')
                        ->url()
                        ->rules(['nullable', 'url:http,https', 'max:2048'])
                        ->maxLength(2048),
                ]),
        ]);
    }
}
