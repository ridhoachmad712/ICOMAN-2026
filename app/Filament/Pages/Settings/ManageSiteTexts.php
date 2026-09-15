<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteText;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use UnitEnum;

/**
 * Menyunting seluruh label website tanpa menyentuh kode.
 *
 * Daftar kuncinya diambil dari berkas di lang/ — itu tetap sumber katalognya,
 * jadi label baru yang ditambahkan di kode langsung muncul di sini. Yang
 * disimpan ke database hanya yang benar-benar diubah; mengosongkan sebuah
 * isian mengembalikan teks bawaannya.
 */
class ManageSiteTexts extends Page
{
    /** Judul tab per grup. Daftar grup yang dikelola ada di SiteText. */
    public const GROUP_LABELS = [
        'nav' => 'Menu & Navigasi',
        'site' => 'Halaman Publik',
        'review' => 'Panduan Reviewer',
    ];

    /** @return array<string, string> */
    public static function groups(): array
    {
        return collect(SiteText::MANAGED_GROUPS)
            ->mapWithKeys(fn (string $group) => [$group => static::GROUP_LABELS[$group] ?? ucfirst($group)])
            ->all();
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Teks Website';

    protected static ?string $navigationLabel = 'Teks Website';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperadmin() ?? false;
    }

    public function mount(): void
    {
        $state = [];

        foreach (static::groups() as $group => $label) {
            foreach (static::keysFor($group) as $key) {
                foreach (static::locales() as $locale => $localeLabel) {
                    $state[static::fieldName($group, $key, $locale)] = static::currentValue($group, $key, $locale);
                }
            }
        }

        $this->form->fill($state);
    }

    /**
     * Susunan halaman: keterangan singkat, lalu formulir beserta tombol
     * simpannya. Filament v4 merender halaman lewat skema `content`.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Semua label yang tampil di website publik. Kosongkan sebuah isian untuk mengembalikan teks bawaannya. Teks yang berasal dari data — nama pembicara, berita, tanggal penting — diubah dari menunya masing-masing.'),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('groups')->tabs(
                    collect(static::groups())
                        ->map(fn (string $label, string $group) => Tabs\Tab::make($label)->schema(static::fieldsFor($group)))
                        ->values()
                        ->all()
                ),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetAll')
                ->label('Kembalikan Semua ke Bawaan')
                ->color('gray')
                ->icon('heroicon-o-arrow-uturn-left')
                ->requiresConfirmation()
                ->modalHeading('Kembalikan semua teks ke bawaan?')
                ->modalDescription('Seluruh suntingan Anda dihapus dan website memakai teks asli lagi. Tidak bisa dibatalkan.')
                ->action(function (): void {
                    SiteText::all()->each->delete();
                    $this->mount();

                    Notification::make()->title('Semua teks dikembalikan ke bawaan.')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = 0;

        foreach (static::groups() as $group => $label) {
            foreach (static::keysFor($group) as $key) {
                $values = [];

                foreach (array_keys(static::locales()) as $locale) {
                    $typed = trim((string) ($state[static::fieldName($group, $key, $locale)] ?? ''));
                    $default = static::defaultValue($group, $key, $locale);

                    // Sama dengan bawaan (atau dikosongkan) berarti "tidak
                    // disunting" — jangan simpan salinan yang tak perlu.
                    if ($typed !== '' && $typed !== $default) {
                        $values[$locale] = $typed;
                    }
                }

                $record = SiteText::firstWhere('key', $group.'.'.$key);

                if ($values === []) {
                    $record?->delete();

                    continue;
                }

                if ($record) {
                    $record->setTranslations('value', $values)->save();
                } else {
                    SiteText::create(['key' => $group.'.'.$key, 'value' => $values]);
                }

                $changed++;
            }
        }

        // Teks yang sedang dipakai halaman ini sendiri ikut berubah.
        $this->mount();

        Notification::make()
            ->title('Teks website disimpan.')
            ->body($changed === 0 ? 'Semua teks memakai bawaan.' : $changed.' teks disunting dari bawaannya.')
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    public static function locales(): array
    {
        return ['id' => 'Indonesia', 'en' => 'English'];
    }

    /** @return array<int, string> */
    public static function keysFor(string $group): array
    {
        $keys = [];

        foreach (array_keys(static::locales()) as $locale) {
            $path = lang_path($locale.'/'.$group.'.php');

            if (! File::exists($path)) {
                continue;
            }

            // Label bersarang (mis. committee_categories.steering) diratakan
            // dengan notasi titik supaya ikut bisa disunting.
            $flat = Arr::dot(require $path);
            $keys = array_merge($keys, array_keys(array_filter($flat, 'is_string')));
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    private static function fieldsFor(string $group): array
    {
        return collect(static::keysFor($group))
            ->map(fn (string $key) => Section::make(static::humanize($key))
                ->description('Kunci: '.$group.'.'.$key)
                ->columns(2)
                ->collapsed()
                ->schema(
                    collect(static::locales())
                        ->map(function (string $label, string $locale) use ($group, $key) {
                            $default = static::defaultValue($group, $key, $locale);
                            // Paragraf panjang tidak terbaca di satu baris.
                            $field = strlen($default) > 100
                                ? Textarea::make(static::fieldName($group, $key, $locale))->rows(4)
                                : TextInput::make(static::fieldName($group, $key, $locale));

                            return $field
                                ->label($label)
                                ->placeholder($default)
                                ->helperText('Kosongkan untuk memakai teks bawaan.');
                        })
                        ->values()
                        ->all()
                ))
            ->all();
    }

    private static function fieldName(string $group, string $key, string $locale): string
    {
        // Titik akan dibaca Livewire sebagai kedalaman array, jadi diganti.
        return $group.'__'.str_replace('.', '_', $key).'__'.$locale;
    }

    /** Teks asli dari berkas lang, tanpa suntingan. */
    private static function defaultValue(string $group, string $key, string $locale): string
    {
        static $files = [];

        $path = lang_path($locale.'/'.$group.'.php');
        $files[$path] ??= File::exists($path) ? require $path : [];

        return (string) data_get($files[$path], $key, '');
    }

    /** Teks yang sedang tampil di website: suntingan bila ada, selain itu bawaan. */
    private static function currentValue(string $group, string $key, string $locale): string
    {
        $override = data_get(SiteText::overrides(), $locale.'.'.$group.'.'.$key);

        return (string) ($override ?? '');
    }

    private static function humanize(string $key): string
    {
        return ucfirst(str_replace(['_', '.'], [' ', ' › '], $key));
    }
}
