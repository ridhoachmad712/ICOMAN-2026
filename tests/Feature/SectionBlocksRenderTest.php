<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Download;
use App\Models\Edition;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\ImportantDate;
use App\Models\News;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\RegistrationFee;
use App\Models\Schedule;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\Topic;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tiap blok dirender dengan datanya benar-benar terisi.
 *
 * Ini menutup celah yang membuat beranda produksi error 500: di mesin
 * pengembangan tabel sponsor kosong sehingga bloknya selalu dilewati, dan
 * variabel yang salah nama di dalamnya tidak pernah tersentuh.
 */
class SectionBlocksRenderTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::create([
            'name' => 'ICOMAN 2026',
            'is_active' => true,
            'start_date' => now()->addMonths(2),
            'end_date' => now()->addMonths(2)->addDay(),
            'theme' => 'Tema Konferensi',
        ]);

        $this->seedEveryKindOfContent();
    }

    /** Satu baris untuk tiap jenis data yang bisa ditampilkan blok. */
    private function seedEveryKindOfContent(): void
    {
        $id = $this->edition->id;

        Speaker::create(['edition_id' => $id, 'name' => 'Prof Nyata', 'type' => 'keynote', 'order' => 1, 'is_published' => true]);
        Topic::create(['edition_id' => $id, 'title' => ['id' => 'Topik Satu', 'en' => 'Topic One'], 'order' => 1]);
        RegistrationFee::create([
            'edition_id' => $id, 'category' => ['id' => 'Presenter', 'en' => 'Presenter'],
            'audience' => 'presenter', 'registrant_category' => 'general',
            'price_regular' => 400_000, 'currency' => 'IDR', 'order' => 1,
        ]);
        ImportantDate::create(['edition_id' => $id, 'label' => ['id' => 'Batas Abstrak', 'en' => 'Abstract Deadline'], 'date' => now()->addMonth(), 'order' => 1]);
        Schedule::create(['edition_id' => $id, 'day_date' => now()->addMonths(2), 'time_start' => '09:00', 'time_end' => '10:00', 'title' => ['id' => 'Pembukaan', 'en' => 'Opening'], 'order' => 1]);
        Committee::create(['edition_id' => $id, 'category' => 'steering', 'name' => 'Dr Panitia', 'role_title' => ['id' => 'Ketua', 'en' => 'Chair'], 'order' => 1, 'is_published' => true]);
        Gallery::create(['edition_id' => $id, 'caption' => ['id' => 'Foto', 'en' => 'Photo'], 'order' => 1]);
        News::create([
            'edition_id' => $id, 'slug' => 'kabar-pertama',
            'title' => ['id' => 'Kabar Pertama', 'en' => 'First News'],
            'excerpt' => ['id' => 'Ringkas.', 'en' => 'Short.'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
            'published_at' => now()->subDay(), 'is_published' => true,
        ]);
        Faq::create(['edition_id' => $id, 'question' => ['id' => 'Tanya?', 'en' => 'Question?'], 'answer' => ['id' => 'Jawab.', 'en' => 'Answer.'], 'order' => 1]);
        Sponsor::create(['edition_id' => $id, 'name' => 'Sponsor Utama', 'tier' => 'platinum', 'order' => 1, 'is_published' => true]);
        Download::create(['edition_id' => $id, 'title' => ['id' => 'Template', 'en' => 'Template'], 'category' => 'template', 'order' => 1]);

        foreach (['about', 'publication', 'call-for-papers'] as $slug) {
            Page::create([
                'slug' => $slug,
                'title' => ['id' => ucfirst($slug), 'en' => ucfirst($slug)],
                'content' => ['id' => '<p>Isi halaman.</p>', 'en' => '<p>Page body.</p>'],
                'is_published' => true,
            ]);
        }
    }

    /** Susunan bawaan beranda, dengan semua bloknya benar-benar punya isi. */
    public function test_the_default_homepage_renders_with_every_block_filled(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Prof Nyata')
            ->assertSee('Sponsor Utama')
            ->assertSee('First News');
    }

    /** @return array<int, array{0: string}> */
    public static function blockTypes(): array
    {
        return array_map(fn (string $type) => [$type], array_keys(PageSection::TYPES));
    }

    /**
     * Tiap jenis blok dipasang sendirian di beranda dan harus tetap merender
     * halaman tanpa error — termasuk blok yang jarang dipakai.
     */
    #[DataProvider('blockTypes')]
    public function test_each_block_type_renders_on_its_own(string $type): void
    {
        PageSection::create([
            'target' => 'home',
            'type' => $type,
            'order' => 0,
            'heading' => ['id' => 'Judul Blok', 'en' => 'Block Heading'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
            'settings' => ['page_slug' => 'about', 'button_label' => 'Klik', 'button_url' => 'https://example.test'],
        ]);

        $this->get(route('home'))->assertOk();
    }

    /** Halaman bawaan lain juga harus utuh saat datanya lengkap. */
    public function test_every_structured_page_renders_with_data(): void
    {
        foreach (['speakers', 'committee', 'call-for-papers', 'important-dates', 'program', 'registration', 'faq'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }
}
