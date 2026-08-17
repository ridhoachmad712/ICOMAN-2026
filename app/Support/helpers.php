<?php

use App\Models\Edition;
use App\Settings\SiteSettings;

if (! function_exists('siteSettings')) {
    /** Instance SiteSettings global (di-cache per-request). */
    function siteSettings(): SiteSettings
    {
        return once(fn () => app(SiteSettings::class));
    }
}

if (! function_exists('countries')) {
    /** Map ISO2 => nama negara. */
    function countries(): array
    {
        return config('countries', []);
    }
}

if (! function_exists('countryCode')) {
    /**
     * Normalisasi input negara (ISO2 atau nama) menjadi kode ISO2 upper-case.
     * Mendukung data lama yang menyimpan nama lengkap ("Singapore").
     */
    function countryCode(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);
        $map = countries();

        if (strlen($value) === 2 && isset($map[strtoupper($value)])) {
            return strtoupper($value);
        }

        $found = array_search(strtolower($value), array_map('strtolower', $map), true);

        return $found !== false ? $found : null;
    }
}

if (! function_exists('countryName')) {
    function countryName(?string $value): ?string
    {
        $code = countryCode($value);

        return $code ? countries()[$code] : ($value ?: null);
    }
}

if (! function_exists('flagUrl')) {
    /** URL bendera (flagcdn) dari ISO2/nama negara, atau null jika tak dikenal. */
    function flagUrl(?string $value, string $size = '40x30'): ?string
    {
        $code = countryCode($value);

        return $code ? "https://flagcdn.com/{$size}/".strtolower($code).'.png' : null;
    }
}

if (! function_exists('currentEdition')) {
    /**
     * Edition yang sedang aktif (is_active = true). Di-cache per-request.
     * Dipakai konsisten untuk scope query publik & default form admin.
     */
    function currentEdition(): ?Edition
    {
        return once(fn () => Edition::query()->where('is_active', true)->first());
    }
}
