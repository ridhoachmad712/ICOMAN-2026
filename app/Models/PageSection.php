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
    ];

    /** Blok yang menampilkan daftar dan bisa dibatasi jumlahnya. */
    public const LIMITED_TYPES = ['speakers', 'gallery', 'news', 'faq', 'important_dates', 'topics'];

    /** Blok yang menarik isi sebuah halaman CMS. */
    public const PAGE_TYPES = ['page_content'];

    /** Susunan bawaan beranda — dipakai selama admin belum menyusunnya sendiri. */
    public const HOME_DEFAULTS = [
        ['type' => 'hero'],
        ['type' => 'organizer'],
        ['type' => 'page_content', 'settings' => ['page_slug' => 'about', 'layout' => 'split']],
        ['type' => 'speakers', 'heading_key' => 'site.keynote_speakers'],
        ['type' => 'topics', 'heading_key' => 'site.call_for_papers', 'subheading_key' => 'site.topics'],
        ['type' => 'fees', 'heading_key' => 'site.registration_fees', 'eyebrow_key' => 'site.home_investment'],
        ['type' => 'important_dates', 'heading_key' => 'site.important_dates'],
        ['type' => 'page_content', 'settings' => ['page_slug' => 'publication'], 'heading_key' => 'site.publication_indexing'],
        ['type' => 'gallery', 'heading_key' => 'site.gallery', 'settings' => ['limit' => 6]],
        ['type' => 'news', 'heading_key' => 'site.latest_news', 'settings' => ['limit' => 3]],
        ['type' => 'faq', 'heading_key' => 'site.faq_title', 'settings' => ['limit' => 5]],
        ['type' => 'sponsors'],
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

        return $target === 'home' ? static::defaultsFor(self::HOME_DEFAULTS) : collect();
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaults
     * @return Collection<int, static>
     */
    public static function defaultsFor(array $defaults): Collection
    {
        return collect($defaults)->map(function (array $default, int $index): self {
            $section = new self([
                'target' => 'home',
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

        $created = 0;

        foreach (static::defaultsFor(self::HOME_DEFAULTS) as $section) {
            $section->save();
            $created++;
        }

        return $created;
    }
}
