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

if (! function_exists('manuscriptTemplatePath')) {
    /** Path template naskah bila panitia sudah menyediakannya, selain itu null. */
    function manuscriptTemplatePath(): ?string
    {
        $path = resource_path('documents/manuscript-template.docx');

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('countryOptions')) {
    /**
     * Daftar negara untuk dropdown: Indonesia didahulukan (mayoritas peserta),
     * sisanya menyusul urut abjad. Kunci = ISO2, nilai = nama negara.
     *
     * @return array<string, string>
     */
    function countryOptions(): array
    {
        $all = countries();
        asort($all, SORT_NATURAL | SORT_FLAG_CASE);

        $options = [];
        if (isset($all['ID'])) {
            $options['ID'] = $all['ID'];
            unset($all['ID']);
        }

        return $options + $all;
    }
}

if (! function_exists('canEditPages')) {
    /**
     * Siapa yang boleh menyunting halaman langsung di atas tampilannya.
     * Dipakai sebagai gerbang mode sunting di halaman publik — dan diperiksa
     * ulang di setiap aksi penyimpanan, bukan hanya saat merender.
     */
    function canEditPages(): bool
    {
        return auth('web')->user()?->hasAnyRole(['superadmin', 'content_admin']) ?? false;
    }
}

if (! function_exists('pageEditMode')) {
    /** Mode sunting hanya menyala bila diminta lewat ?edit=1 DAN penggunanya berwenang. */
    function pageEditMode(): bool
    {
        return request()->boolean('edit') && canEditPages();
    }
}
