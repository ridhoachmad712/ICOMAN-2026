<?php

namespace Tests\Feature;

use App\Filament\Resources\PageSections\Pages\EditPageSection;
use App\Models\Edition;
use App\Models\PageSection;
use App\Models\User;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kendali tampilan per blok dan tipografi menyeluruh.
 *
 * Sebelumnya ukuran judul, warna latar, jarak, dan perataan terkunci di kelas
 * utility tiap blok — tidak ada satu pun isian untuk mengubahnya dari admin.
 */
class SectionAppearanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function block(array $appearance = [], array $settings = []): string
    {
        PageSection::create([
            'target' => 'home',
            'type' => 'rich_text',
            'order' => 0,
            'heading' => ['id' => 'Judul Blok', 'en' => 'Block Heading'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
            'settings' => array_merge($settings, $appearance ? ['appearance' => $appearance] : []),
        ]);

        return $this->get(route('home'))->assertOk()->getContent();
    }

    /** Blok yang tidak diatur tidak boleh berubah sedikit pun. */
    public function test_an_untouched_block_carries_no_style_of_its_own(): void
    {
        $html = $this->block();

        $this->assertStringContainsString('ps-block', $html);
        $this->assertStringNotContainsString('--ps-heading', $html);
        $this->assertStringNotContainsString('--ps-bg', $html);
    }

    public function test_the_admin_can_set_the_heading_and_text_size(): void
    {
        $html = $this->block(['heading_size' => 48, 'text_size' => 20]);

        $this->assertStringContainsString('--ps-heading:48px', $html);
        $this->assertStringContainsString('--ps-text:20px', $html);
    }

    public function test_background_padding_alignment_and_width_are_settable(): void
    {
        $html = $this->block([
            'background' => '#112233',
            'text_color' => '#ffffff',
            'padding_top' => 120,
            'padding_bottom' => 40,
            'align' => 'center',
            'max_width' => 900,
        ]);

        foreach (['--ps-bg:#112233', '--ps-color:#ffffff', '--ps-pt:120px', '--ps-pb:40px', '--ps-align:center', '--ps-width:900px'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    /** Nilai yang tidak masuk akal diabaikan, bukan dituliskan mentah ke CSS. */
    public function test_nonsense_values_are_dropped(): void
    {
        $html = $this->block([
            'background' => 'red; content: url(https://jahat.test)',
            'heading_size' => 9999,
            'align' => 'diagonal',
        ]);

        $this->assertStringNotContainsString('jahat.test', $html);
        $this->assertStringNotContainsString('--ps-heading', $html);
        $this->assertStringNotContainsString('--ps-align', $html);
    }

    public function test_the_column_count_reaches_the_page(): void
    {
        $html = $this->block(['columns' => 3]);

        $this->assertStringContainsString('ps-cols-3', $html);
    }

    // --- Blok kolom bebas ---------------------------------------------------

    public function test_a_columns_block_renders_each_column(): void
    {
        PageSection::create([
            'target' => 'home',
            'type' => 'columns',
            'order' => 0,
            'settings' => ['columns' => [
                ['text_id' => '<p>Kolom kiri.</p>', 'text_en' => '<p>Left column.</p>', 'button_label' => 'Selengkapnya', 'button_url' => 'https://example.test'],
                ['text_id' => '<p>Kolom kanan.</p>', 'text_en' => '<p>Right column.</p>'],
            ]],
        ]);

        app()->setLocale('en');
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Left column.', $html);
        $this->assertStringContainsString('Right column.', $html);
        $this->assertStringContainsString('https://example.test', $html);
    }

    /** Blok kolom tanpa isi dilewati, seperti blok lain yang kosong. */
    public function test_an_empty_columns_block_is_skipped(): void
    {
        PageSection::create([
            'target' => 'home', 'type' => 'columns', 'order' => 0,
            'heading' => ['id' => 'Kolom Kosong', 'en' => 'Empty Columns'],
            'settings' => ['columns' => []],
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('Empty Columns');
    }

    // --- Tipografi menyeluruh ----------------------------------------------

    public function test_the_fonts_follow_the_site_settings(): void
    {
        app(SiteSettings::class)->fill([
            'font_heading' => 'Playfair Display',
            'font_body' => 'Lora',
            'base_font_size' => 18,
        ])->save();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Playfair+Display', $html);
        $this->assertStringContainsString('family=Lora', $html);
        $this->assertStringContainsString("--font-display: 'Playfair Display'", $html);
        $this->assertStringContainsString('font-size: 18px', $html);
    }

    /** Nama huruf di luar daftar tidak boleh ikut masuk ke URL Google Fonts. */
    public function test_an_unknown_font_falls_back_to_the_default(): void
    {
        app(SiteSettings::class)->fill(['font_heading' => 'Comic Sans"); evil('])->save();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('evil(', $html);
        $this->assertStringContainsString('Space+Grotesk', $html);
    }

    // --- Lewat formulir admin, bukan lewat model ----------------------------

    /**
     * Inti keluhannya: pengaturan disimpan lewat formulir admin tapi tidak
     * berubah apa-apa. Isian tampilan menulis ke atribut `appearance`, padahal
     * nilainya dibaca dari kolom `settings` — jadi tersimpan ke tempat yang
     * tidak ada dan diam-diam hilang saat disimpan.
     *
     * Tes sebelumnya menulis langsung ke model, jadi jalur yang benar-benar
     * dipakai admin tidak pernah teruji.
     */
    public function test_appearance_saved_through_the_admin_form_reaches_the_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-tampilan@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $section = PageSection::create([
            'target' => 'home',
            'type' => 'rich_text',
            'order' => 0,
            'heading' => ['id' => 'Judul Blok', 'en' => 'Block Heading'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
        ]);

        Livewire::test(EditPageSection::class, ['record' => $section->getRouteKey()])
            ->assertOk()
            ->fillForm([
                'settings.appearance.heading_size' => 48,
                'settings.appearance.background' => '#112233',
                'settings.appearance.align' => 'center',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $section->refresh();

        $this->assertSame(48, (int) $saved->setting('appearance.heading_size'), 'Ukuran judul tidak tersimpan ke kolom settings.');
        $this->assertSame('#112233', $saved->setting('appearance.background'));

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('--ps-heading:48px', $html);
        $this->assertStringContainsString('--ps-bg:#112233', $html);
        $this->assertStringContainsString('--ps-align:center', $html);
    }

    /** Pengaturan lain di blok yang sama tidak boleh ikut terhapus. */
    public function test_saving_appearance_keeps_the_other_block_settings(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-tampilan2@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $section = PageSection::create([
            'target' => 'home',
            'type' => 'rich_text',
            'order' => 0,
            'heading' => ['id' => 'Judul', 'en' => 'Heading'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
            'settings' => ['tinted' => true],
        ]);

        Livewire::test(EditPageSection::class, ['record' => $section->getRouteKey()])
            ->fillForm(['settings.appearance.text_size' => 20])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $section->refresh();

        $this->assertSame(20, (int) $saved->setting('appearance.text_size'));
        $this->assertTrue((bool) $saved->setting('tinted'), 'Setelan lama pada blok yang sama ikut hilang.');
    }

    // --- Elemen baru --------------------------------------------------------

    public function test_a_buttons_block_renders_each_button(): void
    {
        app()->setLocale('en');
        PageSection::create([
            'target' => 'home', 'type' => 'buttons', 'order' => 0,
            'settings' => ['buttons' => [
                ['label_id' => 'Daftar', 'label_en' => 'Register', 'url' => 'https://example.test/daftar', 'style' => 'primary'],
                ['label_id' => 'Panduan', 'label_en' => 'Guide', 'url' => 'https://example.test/panduan', 'style' => 'outline'],
            ]],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Register', $html);
        $this->assertStringContainsString('https://example.test/panduan', $html);
    }

    public function test_an_accordion_block_renders_its_questions(): void
    {
        app()->setLocale('en');
        PageSection::create([
            'target' => 'home', 'type' => 'accordion', 'order' => 0,
            'settings' => ['items' => [
                ['question_id' => 'Kapan?', 'question_en' => 'When?', 'answer_id' => '<p>Nanti.</p>', 'answer_en' => '<p>Later.</p>'],
            ]],
        ]);

        $this->get(route('home'))->assertOk()->assertSee('When?')->assertSee('Later.', escape: false);
    }

    /** Hanya YouTube dan Vimeo yang boleh disematkan. */
    public function test_a_video_block_only_embeds_known_providers(): void
    {
        PageSection::create([
            'target' => 'home', 'type' => 'video', 'order' => 0,
            'settings' => ['video_url' => 'https://www.youtube.com/watch?v=abc123'],
        ]);

        $this->get(route('home'))->assertOk()->assertSee('https://www.youtube.com/embed/abc123', escape: false);
    }

    public function test_a_video_from_an_unknown_provider_is_not_embedded(): void
    {
        PageSection::create([
            'target' => 'home', 'type' => 'video', 'order' => 0,
            'heading' => ['id' => 'Video Kami', 'en' => 'Our Video'],
            'settings' => ['video_url' => 'https://situs-asing.test/nonton?v=abc'],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('situs-asing.test', $html);
        $this->assertStringNotContainsString('Our Video', $html);
    }

    public function test_stats_cards_quote_and_spacer_render(): void
    {
        app()->setLocale('en');
        PageSection::create(['target' => 'home', 'type' => 'stats', 'order' => 0, 'settings' => ['stats' => [['value' => '120+', 'label_en' => 'Papers']]]]);
        PageSection::create(['target' => 'home', 'type' => 'cards', 'order' => 1, 'settings' => ['cards' => [['title_en' => 'Fast Review', 'text_en' => 'Two weeks.']]]]);
        PageSection::create(['target' => 'home', 'type' => 'quote', 'order' => 2, 'content' => ['id' => '<p>Kutipan.</p>', 'en' => '<p>A quote.</p>'], 'subheading' => ['id' => 'Ketua', 'en' => 'Chair']]);
        PageSection::create(['target' => 'home', 'type' => 'spacer', 'order' => 3, 'settings' => ['height' => 80]]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('120+', $html);
        $this->assertStringContainsString('Fast Review', $html);
        $this->assertStringContainsString('A quote.', $html);
        $this->assertStringContainsString('height: 80px', $html);
    }

    // --- Gambar latar & ukuran ponsel ---------------------------------------

    /** Gambar latar hanya diterima bila berkasnya memang ada di disk publik. */
    public function test_a_background_image_that_does_not_exist_is_ignored(): void
    {
        $html = $this->block(['background_image' => 'sections/tidak-ada.jpg']);

        $this->assertStringNotContainsString('--ps-image', $html);
    }

    public function test_a_real_background_image_and_overlay_reach_the_page(): void
    {
        Storage::disk('public')->put('sections/latar.jpg', 'isi');

        $html = $this->block(['background_image' => 'sections/latar.jpg', 'overlay' => 50]);

        $this->assertStringContainsString('--ps-image:url(', $html);
        $this->assertStringContainsString('sections/latar.jpg', $html);
        $this->assertStringContainsString('--ps-overlay:0.5', $html);

        Storage::disk('public')->delete('sections/latar.jpg');
    }

    public function test_mobile_sizes_are_kept_separate(): void
    {
        $html = $this->block(['heading_size' => 48, 'heading_size_mobile' => 28]);

        $this->assertStringContainsString('--ps-heading:48px', $html);
        $this->assertStringContainsString('--ps-heading-sm:28px', $html);
    }

    // --- Pratinjau ----------------------------------------------------------

    /** Pratinjau muncul di dalam editor blok, bukan cuma tautan ke tab lain. */
    public function test_the_editor_shows_a_preview_of_the_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-pratinjau@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $section = PageSection::create([
            'target' => 'home', 'type' => 'rich_text', 'order' => 0,
            'heading' => ['id' => 'Judul', 'en' => 'Heading'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
        ]);

        $html = Livewire::test(EditPageSection::class, ['record' => $section->getRouteKey()])
            ->assertOk()
            ->html();

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString(route('home'), $html);
        // Selebar formulir, dengan pilihan lebar layar dan mode layar penuh.
        $this->assertStringContainsString('Layar penuh', $html);
        $this->assertStringContainsString('Ponsel', $html);
    }

    /**
     * Di dalam pratinjau, tombol masuk mode sunting tidak ditawarkan — editor
     * di dalam editor hanya membingungkan.
     */
    public function test_the_preview_does_not_offer_edit_mode_inside_itself(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::create(['name' => 'Super', 'email' => 'super-bingkai@example.test', 'password' => 'secret-password']);
        $admin->assignRole('superadmin');
        $this->actingAs($admin, 'web');

        $inside = $this->get(route('home', ['preview' => 123]))->assertOk()->getContent();
        $normal = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Sunting halaman', $inside);
        $this->assertStringContainsString('Sunting halaman', $normal);
    }

    public function test_every_block_target_knows_its_public_page(): void
    {
        foreach (['home', 'speakers', 'committee', 'call-for-papers', 'important-dates', 'schedule', 'registration', 'faq', 'downloads'] as $target) {
            $section = new PageSection(['target' => $target, 'type' => 'rich_text']);

            $this->assertNotNull($section->publicUrl(), 'Halaman '.$target.' tidak punya alamat pratinjau.');
        }
    }
}
