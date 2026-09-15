<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Satu blok penyusun halaman. Jenis blok menentukan data apa yang ditarik dan
 * berkas tampilan mana yang dipakai — lihat resources/views/sections/.
 */
class PageSection extends Model implements HasMedia
{
    use HasTranslations, InteractsWithMedia;

    public array $translatable = ['eyebrow', 'heading', 'subheading', 'content'];

    protected $fillable = [
        'edition_id',
        'target',
        'type',
        'eyebrow',
        'heading',
        'subheading',
        'content',
        'settings',
        'is_published',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_published' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Jenis blok yang tersedia. Kuncinya dipakai sebagai nama berkas tampilan.
     * HTML mentah sengaja tidak disediakan: siapa pun yang bisa masuk admin
     * akan bisa menyisipkan skrip ke halaman publik.
     */
    public const TYPES = [
        'hero' => 'Hero (kepala halaman)',
        'organizer' => 'Diselenggarakan oleh',
        'rich_text' => 'Teks bebas',
        'page_content' => 'Isi halaman lain',
        'image' => 'Gambar lebar',
        'cta' => 'Ajakan bertindak',
        'columns' => 'Kolom bebas',
        'speakers' => 'Pembicara',
        'topics' => 'Topik (Call for Papers)',
        'fees' => 'Tarif registrasi',
        'important_dates' => 'Tanggal penting',
        'schedule' => 'Jadwal acara',
        'committee' => 'Komite',
        'gallery' => 'Galeri',
        'news' => 'Berita terbaru',
        'faq' => 'FAQ',
        'sponsors' => 'Sponsor',
        'downloads' => 'Unduhan',

        // Blok "halaman penuh": tampilan lengkap seperti halaman aslinya,
        // dipakai sebagai isi utama halaman bawaan.
        'speakers_full' => 'Pembicara (halaman penuh)',
        'committee_full' => 'Komite (halaman penuh)',
        'faq_full' => 'FAQ (halaman penuh)',
        'dates_full' => 'Tanggal penting (halaman penuh)',
        'schedule_full' => 'Jadwal acara (halaman penuh)',
        'downloads_full' => 'Unduhan & panduan (halaman penuh)',
        'cfp_full' => 'Call for Papers (halaman penuh)',
        'registration_full' => 'Registrasi (halaman penuh)',
    ];

    /**
     * Blok yang tidak punya tempat untuk judul sendiri. Hero memakai nama dan
     * tema edisi; blok "halaman penuh" judulnya dibawa kepala halaman, yang
     * disunting lewat Teks Website. Isian judul disembunyikan untuk blok ini
     * supaya tidak ada yang mengetik lalu bertanya-tanya kenapa tak muncul.
     */
    public const HEADINGLESS_TYPES = [
        'hero', 'organizer',
        'speakers_full', 'committee_full', 'faq_full', 'dates_full',
        'schedule_full', 'downloads_full', 'cfp_full', 'registration_full',
    ];

    /** Blok yang menampilkan daftar dan bisa dibatasi jumlahnya. */
    public const LIMITED_TYPES = ['speakers', 'gallery', 'news', 'faq', 'important_dates', 'topics'];

    /** Blok yang menarik isi sebuah halaman CMS. */
    public const PAGE_TYPES = ['page_content'];

    /**
     * Susunan bawaan tiap halaman — dipakai selama admin belum menyusunnya
     * sendiri, sehingga tampilan website tidak berubah sampai ia mau.
     */
    public const DEFAULTS = [
        'speakers' => [['type' => 'speakers_full']],
        'committee' => [['type' => 'committee_full']],
        'faq' => [['type' => 'faq_full']],
        'important-dates' => [['type' => 'dates_full']],
        'schedule' => [['type' => 'schedule_full']],
        'downloads' => [['type' => 'downloads_full']],
        'call-for-papers' => [['type' => 'cfp_full']],
        'registration' => [['type' => 'registration_full']],
    ];

    /** Susunan bawaan beranda — dipakai selama admin belum menyusunnya sendiri. */
    public const HOME_DEFAULTS = [
        ['type' => 'hero'],
        ['type' => 'organizer'],
        // Dukungan mitra ditampilkan lebih awal, tepat di bawah penyelenggara.
        ['type' => 'sponsors'],
        ['type' => 'page_content', 'settings' => ['page_slug' => 'about', 'layout' => 'split']],
        ['type' => 'speakers', 'heading_key' => 'site.keynote_speakers'],
        ['type' => 'topics', 'heading_key' => 'site.call_for_papers', 'subheading_key' => 'site.topics'],
        ['type' => 'fees', 'heading_key' => 'site.registration_fees', 'eyebrow_key' => 'site.home_investment'],
        ['type' => 'important_dates', 'heading_key' => 'site.important_dates'],
        ['type' => 'page_content', 'settings' => ['page_slug' => 'publication'], 'heading_key' => 'site.publication_indexing'],
        ['type' => 'gallery', 'heading_key' => 'site.gallery', 'settings' => ['limit' => 6]],
        ['type' => 'news', 'heading_key' => 'site.latest_news', 'settings' => ['limit' => 3]],
        ['type' => 'faq', 'heading_key' => 'site.faq_title', 'settings' => ['limit' => 5]],
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('section')->useDisk('public')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('wide')->width(1600)->format('webp')->nonQueued();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Gaya tampilan yang dipilih admin, diterjemahkan jadi custom property CSS
     * pada pembungkus blok.
     *
     * Hanya nilai yang benar-benar diisi yang dikeluarkan. Aturan gayanya
     * dipasang dengan selector [style*="--ps-..."], jadi blok yang tidak diatur
     * tetap memakai tampilan bawaannya — tanpa perlu nilai cadangan palsu.
     */
    public function styleVariables(): string
    {
        $map = [
            '--ps-bg' => $this->cssColor($this->setting('appearance.background')),
            '--ps-color' => $this->cssColor($this->setting('appearance.text_color')),
            '--ps-heading' => $this->cssLength($this->setting('appearance.heading_size')),
            '--ps-text' => $this->cssLength($this->setting('appearance.text_size')),
            '--ps-pt' => $this->cssLength($this->setting('appearance.padding_top')),
            '--ps-pb' => $this->cssLength($this->setting('appearance.padding_bottom')),
            '--ps-width' => $this->cssLength($this->setting('appearance.max_width')),
            '--ps-align' => $this->cssAlign($this->setting('appearance.align')),
        ];

        return collect($map)
            ->filter(fn (?string $value): bool => $value !== null)
            ->map(fn (string $value, string $property): string => $property.':'.$value)
            ->implode(';');
    }

    /** Jumlah kolom untuk blok berisi daftar; null berarti ikut bawaannya. */
    public function columnCount(): ?int
    {
        $columns = (int) $this->setting('appearance.columns', 0);

        return $columns >= 1 && $columns <= 6 ? $columns : null;
    }

    /** Warna hanya diterima dalam bentuk heks, supaya tak bisa disisipi CSS lain. */
    private function cssColor(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : null;
    }

    /** Ukuran diterima sebagai angka piksel dalam batas yang masuk akal. */
    private function cssLength(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $pixels = (int) $value;

        return $pixels >= 0 && $pixels <= 2000 ? $pixels.'px' : null;
    }

    private function cssAlign(mixed $value): ?string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** Nama berkas tampilan blok ini, atau null bila jenisnya tak dikenal lagi. */
    public function view(): ?string
    {
        return array_key_exists($this->type, self::TYPES) ? 'sections.'.$this->type : null;
    }

    /**
     * Susunan yang dipakai sebuah halaman: baris milik admin bila ada, selain
     * itu susunan bawaan. Bawaan dibuat sebagai model yang tidak disimpan,
     * sehingga berkas tampilannya sama persis untuk keduanya.
     *
     * @return Collection<int, static>
     */
    public static function forTarget(string $target): Collection
    {
        $edition = currentEdition();

        $sections = static::query()
            ->published()
            ->where('target', $target)
            ->when($edition, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereNull('edition_id')
                ->orWhere('edition_id', $edition->id)))
            ->with('media')
            ->orderBy('order')
            ->get();

        if ($sections->isNotEmpty()) {
            return $sections->filter(fn (self $section) => $section->view() !== null)->values();
        }

        return static::defaultsFor(
            $target === 'home' ? self::HOME_DEFAULTS : (self::DEFAULTS[$target] ?? []),
            $target,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaults
     * @return Collection<int, static>
     */
    public static function defaultsFor(array $defaults, string $target = 'home'): Collection
    {
        return collect($defaults)->map(function (array $default, int $index) use ($target): self {
            $section = new self([
                'target' => $target,
                'type' => $default['type'],
                'settings' => $default['settings'] ?? null,
                'is_published' => true,
                'order' => $index,
            ]);

            // Judul bawaan tetap mengikuti Teks Website, sehingga masih bisa
            // disunting admin walau bloknya belum disimpan ke database.
            foreach (['eyebrow', 'heading', 'subheading'] as $field) {
                if (isset($default[$field.'_key'])) {
                    $section->setTranslations($field, [
                        'id' => trans($default[$field.'_key'], [], 'id'),
                        'en' => trans($default[$field.'_key'], [], 'en'),
                    ]);
                }
            }

            return $section;
        });
    }

    /** Menyalin susunan bawaan menjadi baris yang bisa disunting admin. */
    public static function installDefaults(string $target = 'home'): int
    {
        if (static::where('target', $target)->exists()) {
            return 0;
        }

        $defaults = $target === 'home' ? self::HOME_DEFAULTS : (self::DEFAULTS[$target] ?? []);
        $created = 0;

        foreach (static::defaultsFor($defaults, $target) as $section) {
            $section->save();
            $created++;
        }

        return $created;
    }
}
