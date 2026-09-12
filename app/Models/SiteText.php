<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

/**
 * Suntingan admin atas label bawaan di lang/. Hanya kunci yang benar-benar
 * diubah yang tersimpan; sisanya tetap memakai berkas bahasa.
 */
class SiteText extends Model
{
    use HasTranslations;

    public array $translatable = ['value'];

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'site-texts.overrides';

    /**
     * Grup lang yang boleh disunting admin. Sengaja dibatasi: pesan validasi
     * dan terjemahan kerangka kerja bukan teks website, dan menimpanya dari
     * admin bisa merusak perilaku aplikasi.
     */
    public const MANAGED_GROUPS = ['site', 'nav'];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Seluruh suntingan sebagai ['locale']['group']['key'] => teks — bentuk
     * yang langsung bisa ditimpakan ke hasil pembacaan berkas bahasa.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function overrides(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, function (): array {
            $map = [];

            foreach (static::all() as $text) {
                [$group, $key] = array_pad(explode('.', $text->key, 2), 2, null);

                if ($key === null) {
                    continue;
                }

                foreach ($text->getTranslations('value') as $locale => $value) {
                    if (filled($value)) {
                        $map[$locale][$group][$key] = $value;
                    }
                }
            }

            return $map;
        });
    }
}
