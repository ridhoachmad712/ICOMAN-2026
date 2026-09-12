<?php

namespace App\Support;

use App\Models\SiteText;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;

/**
 * Membaca label seperti biasa dari lang/, lalu menimpanya dengan suntingan
 * admin dari tabel `site_texts`. Dipasang sebagai loader penerjemah supaya
 * seluruh pemanggilan __() yang sudah ada di view otomatis bisa disunting dari
 * admin — tanpa mengubah satu pun baris di Blade.
 */
class DatabaseTranslationLoader extends FileLoader
{
    public function load($locale, $group, $namespace = null): array
    {
        $lines = parent::load($locale, $group, $namespace);

        // Hanya grup milik aplikasi sendiri, dan hanya yang memang dikelola
        // admin; terjemahan paket serta pesan validasi dibiarkan apa adanya.
        if (($namespace !== null && $namespace !== '*') || ! in_array($group, SiteText::MANAGED_GROUPS, true)) {
            return $lines;
        }

        return array_replace($lines, $this->overridesFor($locale, $group));
    }

    /** @return array<string, string> */
    private function overridesFor(string $locale, string $group): array
    {
        // Saat instalasi awal atau saat migrasi berjalan, tabelnya belum ada —
        // website harus tetap hidup dengan teks bawaan.
        try {
            if (! Schema::hasTable('site_texts')) {
                return [];
            }

            return SiteText::overrides()[$locale][$group] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
