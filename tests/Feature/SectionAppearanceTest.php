<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\PageSection;
use App\Settings\SiteSettings;
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
}
