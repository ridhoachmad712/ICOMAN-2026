<?php

namespace App\Support;

use App\Models\SiteText;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Schema;

/**
 * Membaca label seperti biasa, lalu menimpanya dengan suntingan admin dari
 * tabel `site_texts`. Dipasang sebagai loader penerjemah supaya seluruh
 * pemanggilan __() yang sudah ada di view otomatis bisa disunting dari admin —
 * tanpa mengubah satu pun baris di Blade.
 *
 * Dibungkus di atas loader asli, bukan menggantikannya: loader asli membawa
 * jalur bahasa bawaan framework (pesan validasi) dan namespace milik paket.
 * Versi terdahulu membangun loader sendiri dari nol dan menghilangkan itu
 * semua, sehingga pesan validasi tampil sebagai kunci mentah.
 */
class DatabaseTranslationLoader implements Loader
{
    public function __construct(private readonly Loader $loader) {}

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        // Hanya grup milik aplikasi sendiri, dan hanya yang memang dikelola
        // admin; terjemahan paket serta pesan validasi dibiarkan apa adanya.
        if (($namespace !== null && $namespace !== '*') || ! in_array($group, SiteText::MANAGED_GROUPS, true)) {
            return $lines;
        }

        return array_replace($lines, $this->overridesFor($locale, $group));
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }

    /** Diteruskan apa adanya: dipakai framework & paket untuk menambah jalur bahasa. */
    public function addPath($path): void
    {
        if (method_exists($this->loader, 'addPath')) {
            $this->loader->addPath($path);
        }
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
