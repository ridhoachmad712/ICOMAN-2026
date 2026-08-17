<?php

namespace App\Filament\Pages\Settings;

use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSiteSettings extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Site Settings';

    protected static ?string $navigationLabel = 'Site Settings';

    /** Site settings hanya untuk superadmin (ARCHITECTURE §6). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('conference_name')->required()->maxLength(255),
                        Select::make('default_locale')
                            ->options(['en' => 'English', 'id' => 'Indonesia'])
                            ->required(),
                        FileUpload::make('logo')->image()->disk('public')->directory('site')->visibility('public'),
                        FileUpload::make('favicon')->image()->disk('public')->directory('site')->visibility('public'),
                    ]),

                Section::make('Tema Warna')
                    ->columns(2)
                    ->schema([
                        ColorPicker::make('primary_color'),
                        ColorPicker::make('secondary_color'),
                    ]),

                Section::make('Hero Homepage')
                    ->columns(2)
                    ->schema([
                        TextInput::make('event_location')->label('Lokasi Acara')->placeholder('Makassar, Indonesia'),
                        TextInput::make('event_mode')->label('Format Acara')->placeholder('Hybrid (Onsite & Online)'),
                        FileUpload::make('hero_image')->label('Gambar Hero')->image()->disk('public')->directory('site')->visibility('public')->columnSpanFull(),
                    ]),

                Section::make('Penyelenggara (Host)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('organizer_name')->label('Nama Penyelenggara')->placeholder('Faculty of Economics, Universitas ...'),
                        FileUpload::make('organizer_logo')->label('Logo Penyelenggara')->image()->disk('public')->directory('site')->visibility('public'),
                    ]),

                Section::make('Kontak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')->email(),
                        TextInput::make('contact_whatsapp'),
                        Textarea::make('contact_address')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Media Sosial')
                    ->columns(3)
                    ->schema([
                        TextInput::make('social_instagram')->placeholder('https://instagram.com/...'),
                        TextInput::make('social_twitter')->placeholder('https://x.com/...'),
                        TextInput::make('social_youtube')->placeholder('https://youtube.com/...'),
                    ]),

                Section::make('Lokasi')
                    ->schema([
                        Textarea::make('google_maps_embed_url')
                            ->label('Google Maps Embed URL')
                            ->rows(2),
                    ]),

                Section::make('Rekening Pembayaran Manual')
                    ->description('Ditampilkan ke peserta yang memilih transfer manual.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('bank_name')->label('Nama Bank'),
                        TextInput::make('bank_account_number')->label('No. Rekening'),
                        TextInput::make('bank_account_holder')->label('Atas Nama'),
                    ]),
            ]);
    }
}
