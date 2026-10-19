<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Faq;
use App\Models\Topic;
use Tests\TestCase;

/**
 * Koreksi terjemahan hanya menyentuh versi Indonesia yang masih memuat frasa
 * lama; versi Inggris dan suntingan panitia dibiarkan apa adanya.
 */
class IndonesianTranslationCorrectionTest extends TestCase
{
    public function test_old_indonesian_phrases_are_corrected_and_english_is_untouched(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        $topic = Topic::create([
            'edition_id' => $edition->id,
            'order' => 2,
            'title' => [
                'en' => 'Sub-theme 2: AI-Driven Experiential Marketing and Inclusive Sustainable Consumer Behavior',
                'id' => 'Sub-tema 2: Pemasaran Berbasis Pengalaman Berbasis AI dan Perilaku Konsumen Berkelanjutan yang Inklusif',
            ],
        ]);
        $faq = Faq::create([
            'edition_id' => $edition->id,
            'order' => 1,
            'question' => ['en' => 'Format?', 'id' => 'Format?'],
            'answer' => [
                'en' => 'Each presenter has 3 minutes for Q&A and discussant feedback.',
                'id' => 'Setiap pemakalah punya 3 menit untuk tanya jawab serta masukan dari reviewer.',
            ],
        ]);
        $edited = Topic::create([
            'edition_id' => $edition->id,
            'order' => 3,
            'title' => ['en' => 'Sub-theme 3', 'id' => 'Sub-tema 3: judul yang disunting panitia'],
        ]);

        (require database_path('migrations/2026_10_19_000001_correct_indonesian_translations.php'))->up();

        $this->assertSame(
            'Sub-tema 2: Pemasaran Eksperiensial Berbasis AI dan Perilaku Konsumen yang Inklusif dan Berkelanjutan',
            $topic->refresh()->getTranslation('title', 'id'),
        );
        $this->assertSame(
            'Sub-theme 2: AI-Driven Experiential Marketing and Inclusive Sustainable Consumer Behavior',
            $topic->getTranslation('title', 'en'),
        );
        $this->assertStringEndsWith('masukan dari pembahas (discussant).', $faq->refresh()->getTranslation('answer', 'id'));
        $this->assertSame('Sub-tema 3: judul yang disunting panitia', $edited->refresh()->getTranslation('title', 'id'));
    }
}
