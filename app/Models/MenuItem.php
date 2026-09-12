<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Spatie\Translatable\HasTranslations;

/**
 * Satu butir menu navigasi. Sebelumnya susunan menu tertulis tetap di navbar,
 * sehingga halaman buatan admin tidak pernah bisa muncul di navigasi.
 */
class MenuItem extends Model
{
    use HasTranslations;

    public array $translatable = ['label'];

    protected $fillable = [
        'parent_id',
        'label',
        'type',
        'route_name',
        'page_id',
        'url',
        'opens_in_new_tab',
        'is_published',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'is_published' => 'boolean',
            'order' => 'integer',
        ];
    }

    /** Susunan bawaan — dipakai selama admin belum menyusun menunya sendiri. */
    public const DEFAULTS = [
        ['route' => 'home', 'key' => 'nav.home'],
        ['route' => 'about', 'key' => 'nav.about', 'children' => [
            ['route' => 'about', 'key' => 'nav.about'],
            ['route' => 'committee', 'key' => 'nav.committee'],
            ['route' => 'venue', 'key' => 'nav.venue'],
        ]],
        ['route' => 'program', 'key' => 'nav.program', 'children' => [
            ['route' => 'speakers', 'key' => 'nav.speakers'],
            ['route' => 'call-for-papers', 'key' => 'nav.cfp'],
            ['route' => 'important-dates', 'key' => 'nav.dates'],
            ['route' => 'program', 'key' => 'nav.program'],
            ['route' => 'author-guidelines', 'key' => 'site.templates'],
        ]],
        ['route' => 'registration', 'key' => 'nav.registration'],
        ['route' => 'news.index', 'key' => 'nav.news'],
        ['route' => 'contact', 'key' => 'nav.contact'],
    ];

    /**
     * Halaman bawaan yang boleh dituju sebuah butir menu. Sengaja daftar
     * tertutup: nama route bebas akan membuat menu error begitu route-nya
     * berubah, dan halaman admin/portal tidak boleh ditautkan dari sini.
     *
     * @return array<string, string>
     */
    public static function routeOptions(): array
    {
        $routes = [
            'home' => 'Beranda',
            'about' => 'Tentang',
            'committee' => 'Komite',
            'venue' => 'Lokasi',
            'speakers' => 'Pembicara',
            'call-for-papers' => 'Call for Papers',
            'important-dates' => 'Tanggal Penting',
            'program' => 'Jadwal Acara',
            'author-guidelines' => 'Panduan Penulis',
            'registration' => 'Registrasi',
            'news.index' => 'Berita',
            'contact' => 'Kontak',
            'privacy' => 'Kebijakan Privasi',
        ];

        return collect($routes)->filter(fn ($label, $name) => Route::has($name))->all();
    }

    /** Membuat ulang susunan menu bawaan. Dipakai admin untuk memulai/memulihkan. */
    public static function installDefaults(): int
    {
        $created = 0;
        $order = 0;

        foreach (static::DEFAULTS as $item) {
            $children = $item['children'] ?? [];

            $parent = static::create([
                'label' => static::labelTranslations($item['key']),
                'type' => 'route',
                // Induk yang punya anak dijadikan pembuka sub-menu, bukan tautan.
                'route_name' => $children === [] ? $item['route'] : null,
                'order' => $order++,
            ]);
            $created++;

            $childOrder = 0;
            foreach ($children as $child) {
                static::create([
                    'parent_id' => $parent->id,
                    'label' => static::labelTranslations($child['key']),
                    'type' => 'route',
                    'route_name' => $child['route'],
                    'order' => $childOrder++,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /** @return array<string, string> */
    private static function labelTranslations(string $key): array
    {
        $labels = [];

        foreach (['id', 'en'] as $locale) {
            $labels[$locale] = trans($key, [], $locale);
        }

        return $labels;
    }

    /**
     * Susunan bawaan dalam bentuk siap render. Dipakai selama tabelnya masih
     * kosong, supaya navigasi tidak pernah hilang setelah pemasangan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function fallbackTree(): Collection
    {
        $build = function (array $item): ?array {
            $url = Route::has($item['route']) ? route($item['route']) : null;

            return $url === null ? null : [
                'label' => __($item['key']),
                'url' => $url,
                'route' => $item['route'],
                'new_tab' => false,
                'children' => [],
            ];
        };

        return collect(static::DEFAULTS)
            ->map(function (array $item) use ($build): ?array {
                $children = collect($item['children'] ?? [])->map($build)->filter()->values()->all();
                $self = $build($item);

                if ($children !== []) {
                    return [
                        'label' => __($item['key']),
                        'url' => null,
                        'route' => null,
                        'new_tab' => false,
                        'children' => $children,
                    ];
                }

                return $self;
            })
            ->filter()
            ->values();
    }

    /** Menu yang dipakai website: susunan admin bila ada, selain itu bawaan. */
    public static function forNavigation(): Collection
    {
        $tree = static::tree();

        return $tree->isEmpty() ? static::fallbackTree() : $tree;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Alamat tujuan butir ini, atau null bila tujuannya tidak dapat dipakai —
     * halaman CMS yang dihapus/di-unpublish, atau nama route yang tidak ada
     * lagi. Butir semacam itu disembunyikan, bukan membuat halaman error.
     */
    public function resolveUrl(): ?string
    {
        return match ($this->type) {
            'url' => filled($this->url) ? $this->url : null,
            'page' => $this->page && $this->page->is_published
                ? route('page', ['slug' => $this->page->slug])
                : null,
            default => filled($this->route_name) && Route::has($this->route_name)
                ? route($this->route_name)
                : null,
        };
    }

    /** Nama route aktif yang diwakili butir ini, untuk penanda menu aktif. */
    public function activeRouteName(): ?string
    {
        return $this->type === 'route' ? $this->route_name : null;
    }

    /**
     * Menu siap render: hanya yang tampil dan tujuannya sah. Induk tanpa anak
     * yang sah ikut disembunyikan, kecuali induknya sendiri punya tujuan.
     *
     * @return Collection<int, array{label: string, url: ?string, route: ?string, new_tab: bool, children: array<int, array<string, mixed>>}>
     */
    public static function tree(): Collection
    {
        return static::query()
            ->published()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->published()->with('page'), 'page'])
            ->orderBy('order')
            ->get()
            ->map(function (self $item): ?array {
                $children = $item->children
                    ->map(fn (self $child): ?array => ($url = $child->resolveUrl()) === null ? null : [
                        'label' => $child->label,
                        'url' => $url,
                        'route' => $child->activeRouteName(),
                        'new_tab' => $child->opens_in_new_tab,
                        'children' => [],
                    ])
                    ->filter()
                    ->values()
                    ->all();

                $url = $item->resolveUrl();

                if ($url === null && $children === []) {
                    return null;
                }

                return [
                    'label' => $item->label,
                    'url' => $url,
                    'route' => $item->activeRouteName(),
                    'new_tab' => $item->opens_in_new_tab,
                    'children' => $children,
                ];
            })
            ->filter()
            ->values();
    }
}
