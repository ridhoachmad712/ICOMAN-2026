<?php

namespace Tests\Feature;

use App\Filament\Resources\PageSections\Pages\EditPageSection;
use App\Models\Edition;
use App\Models\PageSection;
use App\Models\User;
use App\Settings\SiteSettings;
use Filament\Facades\Filament;
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
}
