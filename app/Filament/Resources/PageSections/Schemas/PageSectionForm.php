<?php

namespace App\Filament\Resources\PageSections\Schemas;

use App\Models\Page;
use App\Models\PageSection;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class PageSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blok')
                ->columns(2)
                ->schema([
                    Select::make('target')
                        ->label('Halaman')
                        ->options(fn () => static::targetOptions())
                        ->default('home')
                        ->required()
                        ->helperText('Blok ini akan tampil di halaman tersebut.'),

                    Select::make('type')
                        ->label('Jenis blok')
                        ->options(PageSection::TYPES)
                        ->required()
                        ->live()
                        ->helperText('Menentukan isi dan tampilan blok.'),

                    Toggle::make('is_published')->label('Tampilkan')->default(true)->inline(false),

                    Text::make('Judul blok ini mengikuti data acara atau kepala halaman, jadi tidak disetel di sini. Ubah lewat Pengaturan → Teks Website.')
                        ->visible(fn ($get) => in_array($get('type'), PageSection::HEADINGLESS_TYPES, true))
                        ->columnSpanFull(),
                ]),

            Section::make('Judul')
                ->description('Kosongkan bila blok ini tidak perlu judul.')
                ->columns(2)
                ->visible(fn ($get) => ! in_array($get('type'), PageSection::HEADINGLESS_TYPES, true))
                ->schema([
                    TextInput::make('eyebrow.id')->label('Label kecil (Indonesia)'),
                    TextInput::make('eyebrow.en')->label('Label kecil (English)'),
                    TextInput::make('heading.id')->label('Judul (Indonesia)'),
                    TextInput::make('heading.en')->label('Judul (English)'),
                    TextInput::make('subheading.id')->label('Sub-judul (Indonesia)'),
                    TextInput::make('subheading.en')->label('Sub-judul (English)'),
                ]),

            Section::make('Isi')
                ->visible(fn ($get) => in_array($get('type'), ['rich_text', 'image'], true))
                ->schema([
                    RichEditor::make('content.id')->label('Isi (Indonesia)')->columnSpanFull(),
                    RichEditor::make('content.en')->label('Isi (English)')->columnSpanFull(),
                ]),

            Section::make('Gambar')
                ->visible(fn ($get) => $get('type') === 'image')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('section')
                        ->collection('section')
                        ->disk('public')
                        ->image()
                        ->imageEditor()
                        ->columnSpanFull(),
                ]),

            Section::make('Kolom')
                ->description('Tiap kolom diisi bebas: teks, gambar, dan satu tombol.')
                ->visible(fn ($get) => $get('type') === 'columns')
                ->schema([
                    Repeater::make('settings.columns')
                        ->hiddenLabel()
                        ->addActionLabel('Tambah kolom')
                        ->reorderable()
                        ->maxItems(4)
                        ->defaultItems(2)
                        ->columns(2)
                        ->schema([
                            RichEditor::make('text_id')->label('Isi (Indonesia)')->columnSpanFull(),
                            RichEditor::make('text_en')->label('Isi (English)')->columnSpanFull(),
                            FileUpload::make('image')
                                ->label('Gambar')
                                ->image()
                                ->disk('public')
                                ->directory('sections')
                                ->visibility('public')
                                ->columnSpanFull(),
                            TextInput::make('button_label')->label('Teks tombol'),
                            TextInput::make('button_url')->label('Alamat tombol')->url(),
                        ]),
                ]),

            Section::make('Tampilan')
                ->description('Kosongkan isian apa pun untuk memakai tampilan bawaan blok ini.')
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('settings.appearance.heading_size')
                        ->label('Ukuran judul')
                        ->numeric()->minValue(10)->maxValue(200)->suffix('px')
                        ->helperText('Bawaan sekitar 30–36 px.'),

                    TextInput::make('settings.appearance.text_size')
                        ->label('Ukuran teks isi')
                        ->numeric()->minValue(8)->maxValue(100)->suffix('px')
                        ->helperText('Bawaan sekitar 16 px.'),

                    Select::make('settings.appearance.align')
                        ->label('Perataan teks')
                        ->options(['left' => 'Kiri', 'center' => 'Tengah', 'right' => 'Kanan'])
                        ->placeholder('Bawaan blok'),

                    TextInput::make('settings.appearance.columns')
                        ->label('Jumlah kolom')
                        ->numeric()->minValue(1)->maxValue(6)
                        ->helperText('Untuk blok berisi kartu. Di ponsel selalu menumpuk satu kolom.'),

                    ColorPicker::make('settings.appearance.background')->label('Warna latar'),
                    ColorPicker::make('settings.appearance.text_color')->label('Warna teks'),

                    TextInput::make('settings.appearance.padding_top')
                        ->label('Jarak atas')->numeric()->minValue(0)->maxValue(400)->suffix('px'),
                    TextInput::make('settings.appearance.padding_bottom')
                        ->label('Jarak bawah')->numeric()->minValue(0)->maxValue(400)->suffix('px'),

                    TextInput::make('settings.appearance.max_width')
                        ->label('Lebar isi')
                        ->numeric()->minValue(320)->maxValue(2000)->suffix('px')
                        ->helperText('Bawaan sekitar 1280 px.')
                        ->columnSpanFull(),
                ]),

            Section::make('Pengaturan Blok')
                ->columns(2)
                ->visible(fn ($get) => in_array($get('type'), array_merge(
                    PageSection::LIMITED_TYPES,
                    PageSection::PAGE_TYPES,
                    ['cta', 'rich_text'],
                ), true))
                ->schema([
                    TextInput::make('settings.limit')
                        ->label('Jumlah maksimal')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Kosongkan untuk menampilkan semuanya.')
                        ->visible(fn ($get) => in_array($get('type'), PageSection::LIMITED_TYPES, true)),

                    Select::make('settings.page_slug')
                        ->label('Halaman yang ditarik')
                        ->options(fn () => Page::orderBy('slug')->pluck('slug', 'slug'))
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => in_array($get('type'), PageSection::PAGE_TYPES, true)),

                    Select::make('settings.layout')
                        ->label('Tata letak')
                        ->options([
                            'plain' => 'Satu kolom (isi penuh)',
                            'split' => 'Dua kolom (ringkasan + info acara)',
                        ])
                        ->default('plain')
                        ->visible(fn ($get) => in_array($get('type'), PageSection::PAGE_TYPES, true)),

                    TextInput::make('settings.button_label')
                        ->label('Teks tombol')
                        ->visible(fn ($get) => $get('type') === 'cta'),

                    TextInput::make('settings.button_url')
                        ->label('Alamat tombol')
                        ->url()
                        ->visible(fn ($get) => $get('type') === 'cta'),

                    Toggle::make('settings.tinted')
                        ->label('Latar abu-abu')
                        ->inline(false)
                        ->visible(fn ($get) => in_array($get('type'), ['cta', 'rich_text'], true)),
                ]),
        ]);
    }

    /** @return array<string, string> */
    public static function targetOptions(): array
    {
        return collect([
            'home' => 'Beranda',
            'speakers' => 'Pembicara',
            'committee' => 'Komite',
            'call-for-papers' => 'Call for Papers',
            'important-dates' => 'Tanggal Penting',
            'schedule' => 'Jadwal Acara',
            'registration' => 'Registrasi',
            'faq' => 'FAQ',
            'downloads' => 'Unduhan & Panduan',
        ])
            ->merge(
                Page::where('is_published', true)->orderBy('slug')->get()
                    ->mapWithKeys(fn (Page $page) => ['page:'.$page->slug => 'Halaman: '.$page->title])
            )
            ->all();
    }
}
