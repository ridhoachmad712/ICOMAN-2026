<?php

use App\Models\Faq;
use App\Models\News;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\RegistrationFee;
use App\Models\SiteText;
use App\Models\Speaker;
use App\Models\Topic;
use Illuminate\Database\Migrations\Migration;

/**
 * Koreksi terjemahan bahasa Indonesia pada konten awal: frasa yang keliru
 * makna ("Berbasis Pengalaman Berbasis AI", "masukan dari reviewer" untuk
 * discussant), ganda ("secara penuh secara daring"), atau tidak lengkap
 * dibanding versi Inggrisnya. Hanya locale `id` yang disentuh, dan hanya bila
 * frasa lama masih persis ada — suntingan panitia lewat admin tidak tertimpa.
 */
return new class extends Migration
{
    /** Urutan penting: frasa yang lebih panjang lebih dulu. */
    private const CORRECTIONS = [
        'Pemasaran Berbasis Pengalaman Berbasis AI dan Perilaku Konsumen Berkelanjutan yang Inklusif'
            => 'Pemasaran Eksperiensial Berbasis AI dan Perilaku Konsumen yang Inklusif dan Berkelanjutan',
        'Pemasaran Berbasis Pengalaman Berbasis AI'
            => 'Pemasaran Eksperiensial Berbasis AI',
        'diselenggarakan secara penuh secara daring'
            => 'diselenggarakan sepenuhnya secara daring',
        'tanya jawab serta masukan dari reviewer.'
            => 'tanya jawab serta masukan dari pembahas (discussant).',
        'untuk seluruh penulisan paper,'
            => 'untuk seluruh pengiriman paper,',
        'Merumuskan rekomendasi kebijakan strategis berbasis bukti bagi regulator untuk ekosistem bisnis era AI.'
            => 'Merumuskan rekomendasi kebijakan berbasis bukti bagi pembuat kebijakan dan regulator untuk mendorong ekosistem bisnis yang inklusif, tangguh, dan berkelanjutan.',
        '(Sustainable Development Goals/SDGs, standar ISSB, dan kerangka ESG)'
            => '(Sustainable Development Goals/SDGs, regulasi pelaporan keberlanjutan ISSB, dan kerangka ESG)',
        'Special Issue jurnal mitra terakreditasi dan bereputasi.'
            => 'Special Issue jurnal mitra bereputasi.',
        'yang disusun sebagai ringkasan kebijakan strategis bagi para pembuat kebijakan dan pelaku bisnis.'
            => 'yang disusun menjadi policy brief berbasis bukti dan disebarkan kepada para pemangku kepentingan utama.',
        'Mencakup slot presentasi di ruang Zoom,'
            => 'Mencakup slot presentasi di Zoom Breakout Room,',
    ];

    public function up(): void
    {
        foreach ([Topic::class, Speaker::class, Page::class, PageSection::class, Faq::class, News::class, RegistrationFee::class, SiteText::class] as $model) {
            $model::query()->each(function ($record): void {
                foreach ($record->getTranslatableAttributes() as $field) {
                    $translations = $record->getTranslations($field);
                    $value = $translations['id'] ?? null;

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $corrected = str_replace(array_keys(self::CORRECTIONS), array_values(self::CORRECTIONS), $value);

                    if ($corrected !== $value) {
                        $translations['id'] = $corrected;
                        $record->setTranslations($field, $translations);
                    }
                }

                if ($record->isDirty()) {
                    $record->saveQuietly();
                }
            });
        }
    }

    public function down(): void
    {
        // Koreksi editorial tidak dikembalikan ke terjemahan yang keliru.
    }
};
