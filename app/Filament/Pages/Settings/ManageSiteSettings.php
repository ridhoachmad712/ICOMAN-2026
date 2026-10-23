<?php

namespace App\Filament\Pages\Settings;

use App\Services\CertificatePdf;
use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSiteSettings extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Site Settings';

    protected static ?string $navigationLabel = 'Site Settings';

    /** Site settings hanya untuk superadmin (ARCHITECTURE §6). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    /** Pratinjau memakai pengaturan yang sudah disimpan, dengan data contoh. */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('previewPresentationCertificate')
                    ->label('Sertifikat presenter')
                    ->url(route('admin.certificates.preview', 'presentation'))
                    ->openUrlInNewTab(),
                Action::make('previewParticipationCertificate')
                    ->label('Sertifikat peserta seminar')
                    ->url(route('admin.certificates.preview', 'participation'))
                    ->openUrlInNewTab(),
            ])
                ->label('Pratinjau sertifikat')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->button(),
        ];
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

                Section::make('Tipografi')
                    ->description('Berlaku untuk seluruh halaman publik sekaligus.')
                    ->columns(3)
                    ->schema([
                        Select::make('font_heading')
                            ->label('Huruf judul')
                            ->options(SiteSettings::FONTS)
                            ->searchable()
                            ->required(),
                        Select::make('font_body')
                            ->label('Huruf isi')
                            ->options(SiteSettings::FONTS)
                            ->searchable()
                            ->required(),
                        TextInput::make('base_font_size')
                            ->label('Ukuran teks dasar')
                            ->helperText('Bawaan 16 px. Seluruh ukuran lain ikut menyesuaikan.')
                            ->numeric()->minValue(12)->maxValue(24)->suffix('px')
                            ->required(),
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
                        TextInput::make('whatsapp_group_url')
                            ->label('Link grup WhatsApp peserta')
                            ->url()
                            ->placeholder('https://chat.whatsapp.com/...')
                            ->helperText('Hanya tampil di portal bagi presenter dan peserta yang pembayarannya sudah lunas. Kosongkan untuk menyembunyikan.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Zoom Konferensi')
                    ->description('Tampil di dashboard portal hanya bagi peserta seminar yang sudah lunas, dan presenter yang sudah lunas serta sudah mengumpulkan full paper. Kosongkan link untuk menyembunyikan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('zoom_url')
                            ->label('Link Zoom')
                            ->url()
                            ->placeholder('https://us02web.zoom.us/j/...')
                            ->columnSpanFull(),
                        TextInput::make('zoom_meeting_id')->label('Meeting ID')->maxLength(50),
                        TextInput::make('zoom_passcode')->label('Passcode')->maxLength(50),
                        Textarea::make('zoom_note')
                            ->label('Catatan untuk peserta')
                            ->placeholder('Mis. Ruang dibuka 08.30 WITA. Gunakan nama sesuai akun portal.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Sertifikat')
                    ->description('Dibaca setiap kali sertifikat diunduh, jadi perubahan juga berlaku bagi sertifikat yang sudah terbit (nama, afiliasi, dan judul tetap seperti saat terbit). Simpan dulu, lalu gunakan tombol Pratinjau sertifikat di kanan atas.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('certificate_custom_layout')
                            ->label('Template custom: desain latar sudah memuat judul, teks, logo, dan tanda tangan')
                            ->helperText('Bila aktif, sistem hanya mencetak nama, afiliasi, judul paper, nomor, dan QR code pada posisi di bawah. Bila tidak, sistem mencetak susunan bawaan di atas gambar latar (atau di atas bingkai bawaan bila latar kosong).')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('certificate_signatory_name')->label('Nama penandatangan')->maxLength(150)
                            ->visible(fn ($get) => ! $get('certificate_custom_layout')),
                        TextInput::make('certificate_signatory_title')->label('Jabatan penandatangan')->maxLength(150)
                            ->placeholder('Mis. Ketua Panitia ICOMAN 2026')
                            ->visible(fn ($get) => ! $get('certificate_custom_layout')),
                        FileUpload::make('certificate_signature')
                            ->label('Gambar tanda tangan')
                            ->helperText('PNG berlatar transparan.')
                            ->image()->disk('public')->directory('certificates')->visibility('public')
                            ->visible(fn ($get) => ! $get('certificate_custom_layout')),
                        Tabs::make('certificate_templates')
                            ->columnSpanFull()
                            ->tabs([
                                static::certificateTemplateTab('presentation', 'Presenter'),
                                static::certificateTemplateTab('participation', 'Peserta seminar'),
                            ]),
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

                Section::make('Biaya Tambahan Publikasi')
                    ->description('Opsi penerbitan lanjutan (Jurnal SINTA 3) yang dapat dipilih presenter saat membayar. Nominal ini ditambahkan ke total invoice mereka.')
                    ->schema([
                        TextInput::make('sinta3_fee')
                            ->label('Biaya tambahan Jurnal SINTA 3')
                            ->helperText('Isi 0 bila tidak ada biaya tambahan. Perubahan langsung berlaku untuk invoice berikutnya.')
                            ->numeric()
                            ->minValue(0)
                            ->step(1000)
                            ->prefix('Rp')
                            ->required()
                            ->dehydrateStateUsing(fn ($state): int => (int) $state),
                    ]),

                Section::make('Payment Gateway (BorderPay)')
                    ->description('Kredensial BorderPay dari dashboard project. Key berawalan bp_test_ memakai sandbox (tidak ada uang berpindah), bp_live_ memakai transaksi sungguhan; tidak ada saklar mode tersendiri. Kosongkan untuk memakai nilai dari file .env.')
                    ->schema([
                        TextInput::make('borderpay_api_key')
                            ->label('API Key')
                            ->placeholder('bp_live_...')
                            ->helperText('Rahasia — disimpan ter-enkripsi dan tidak ditampilkan kembali. Isi hanya bila ingin mengubah; kosongkan untuk mempertahankan yang tersimpan.')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            // Jangan pernah render kembali nilai rahasia ke form.
                            ->afterStateHydrated(fn (TextInput $component) => $component->state(''))
                            // Simpan hanya bila admin mengisi; blank = pertahankan nilai lama.
                            ->dehydrated(fn (?string $state): bool => filled($state)),

                        TextInput::make('borderpay_webhook_token')
                            ->label('Webhook Verification Token')
                            ->helperText('Token verifikasi webhook dari dashboard project (bpt_...). Ini token statis, bukan signing secret: karena itu isi kiriman webhook tidak pernah dipercaya dan statusnya selalu ditanyakan ulang ke BorderPay. Disimpan ter-enkripsi dan tidak ditampilkan kembali; kosongkan untuk mempertahankan yang tersimpan.')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            // Jangan pernah render kembali nilai rahasia ke form.
                            ->afterStateHydrated(fn (TextInput $component) => $component->state(''))
                            // Simpan hanya bila admin mengisi; blank = pertahankan nilai lama.
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }

    /** Satu tab template: gambar latar dan (di mode custom) posisi tiap elemen dinamis. */
    private static function certificateTemplateTab(string $type, string $label): Tab
    {
        $isPresentation = $type === 'presentation';
        $fields = [
            FileUpload::make('certificate_background_'.$type)
                ->label('Gambar latar '.$label.' (A4 landscape)')
                ->helperText($isPresentation
                    ? 'Disarankan 3508 × 2480 px (300 dpi), JPG/PNG.'
                    : 'Disarankan 3508 × 2480 px (300 dpi), JPG/PNG. Bila kosong, memakai latar presenter.')
                ->image()->disk('public')->directory('certificates')->visibility('public')
                ->columnSpanFull(),
        ];

        foreach (CertificatePdf::ELEMENTS as $element => $elementLabel) {
            if ($element === 'paper_title' && ! $isPresentation) {
                continue;
            }

            $defaults = CertificatePdf::DEFAULT_LAYOUT[$element];
            $key = 'certificate_layout.'.$type.'.'.$element.'.';
            $inputs = [
                TextInput::make($key.'top')->label('Dari atas (mm)')->numeric()->minValue(0)->maxValue(210)->placeholder((string) $defaults['top']),
                TextInput::make($key.'left')->label('Dari kiri (mm)')->numeric()->minValue(0)->maxValue(297)->placeholder((string) $defaults['left']),
            ];

            if ($element === 'qr') {
                $inputs[] = TextInput::make($key.'size')->label('Ukuran (mm)')->numeric()->minValue(10)->maxValue(80)->placeholder((string) $defaults['size']);
            } else {
                $inputs[] = TextInput::make($key.'width')->label('Lebar kotak (mm)')->numeric()->minValue(10)->maxValue(297)->placeholder((string) $defaults['width']);
                $inputs[] = TextInput::make($key.'size')->label('Ukuran huruf (pt)')->numeric()->minValue(6)->maxValue(72)->placeholder((string) $defaults['size']);
                $inputs[] = Select::make($key.'align')->label('Perataan')
                    ->options(['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'])
                    ->placeholder('Tengah');
                $inputs[] = ColorPicker::make($key.'color')->label('Warna')->placeholder($defaults['color']);
            }

            $fields[] = Fieldset::make($elementLabel)
                ->columns(['default' => 2, 'lg' => $element === 'qr' ? 3 : 6])
                ->schema($inputs)
                ->visible(fn ($get) => (bool) $get('certificate_custom_layout'));
        }

        return Tab::make($label)->schema($fields);
    }
}
