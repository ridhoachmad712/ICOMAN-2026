<?php

namespace Tests\Feature;

use App\Filament\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Resources\PageSections\PageSectionResource;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Speaker;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kustomisasi tahap 2: susunan beranda pindah ke blok yang bisa diurutkan,
 * disembunyikan, dan ditambah dari admin.
 */
class PageBuilderTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function superadmin(string $email = 'super-blok@example.test'): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $user = User::create(['name' => 'Super', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole('superadmin');
        $this->actingAs($user, 'web');

        return $user;
    }

    private function speaker(string $name = 'Prof Contoh'): Speaker
    {
        return Speaker::create([
            'edition_id' => $this->edition->id, 'name' => $name,
            'type' => 'keynote', 'order' => 1, 'is_published' => true,
        ]);
    }

    public function test_the_homepage_uses_the_built_in_layout_while_no_blocks_exist(): void
    {
        $this->assertTrue(PageSection::query()->doesntExist());

        $sections = PageSection::forTarget('home');

        $this->assertGreaterThan(5, $sections->count());
        $this->assertSame('hero', $sections->first()->type);
        $this->get(route('home'))->assertOk();
    }

    /** Judul bawaan tetap mengikuti Teks Website walau bloknya belum disimpan. */
    public function test_built_in_blocks_take_their_heading_from_the_site_texts(): void
    {
        app()->setLocale('en');

        $speakers = PageSection::forTarget('home')->firstWhere('type', 'speakers');

        $this->assertSame(__('site.keynote_speakers'), $speakers->heading);
    }

    public function test_saved_blocks_replace_the_built_in_layout(): void
    {
        PageSection::create([
            'target' => 'home', 'type' => 'rich_text', 'order' => 0,
            'heading' => ['id' => 'Sambutan Ketua', 'en' => 'Chair Welcome'],
            'content' => ['id' => '<p>Selamat datang.</p>', 'en' => '<p>Welcome.</p>'],
        ]);

        $sections = PageSection::forTarget('home');

        $this->assertCount(1, $sections);
        $this->get(route('home'))->assertOk()->assertSee('Chair Welcome');
    }

    public function test_blocks_render_in_the_order_given(): void
    {
        $this->speaker();
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 1, 'heading' => ['en' => 'Second block'], 'content' => ['en' => '<p>b</p>']]);
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 0, 'heading' => ['en' => 'First block'], 'content' => ['en' => '<p>a</p>']]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Second block'), strpos($html, 'First block'));
    }

    public function test_a_hidden_block_is_left_out(): void
    {
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 0, 'heading' => ['en' => 'Visible'], 'content' => ['en' => '<p>a</p>']]);
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 1, 'is_published' => false, 'heading' => ['en' => 'Hidden'], 'content' => ['en' => '<p>b</p>']]);

        $this->get(route('home'))->assertOk()->assertSee('Visible')->assertDontSee('Hidden');
    }

    /** Blok yang datanya kosong tidak boleh menyisakan ruang kosong. */
    public function test_a_block_without_content_is_skipped(): void
    {
        PageSection::create(['target' => 'home', 'type' => 'news', 'order' => 0, 'heading' => ['en' => 'Latest From Us']]);

        $this->get(route('home'))->assertOk()->assertDontSee('Latest From Us');
    }

    /**
     * Kecuali blok pembicara: tanpa data ia menampilkan "Segera Diumumkan",
     * yang memang informasi yang dicari pengunjung.
     */
    public function test_the_speaker_block_announces_that_speakers_are_coming(): void
    {
        app()->setLocale('en');
        PageSection::create(['target' => 'home', 'type' => 'speakers', 'order' => 0, 'heading' => ['en' => 'Our Speakers']]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Our Speakers')
            ->assertSee(__('site.home_to_be_announced'));
    }

    /** Jenis blok yang tak dikenal lagi dilewati, bukan membuat halaman error. */
    public function test_an_unknown_block_type_does_not_break_the_page(): void
    {
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 0, 'heading' => ['en' => 'Good block'], 'content' => ['en' => '<p>a</p>']]);
        PageSection::create(['target' => 'home', 'type' => 'sudah_dihapus', 'order' => 1]);

        $this->get(route('home'))->assertOk()->assertSee('Good block');
    }

    public function test_a_block_limit_caps_how_many_records_show(): void
    {
        $this->speaker('Pembicara Satu');
        $this->speaker('Pembicara Dua');

        PageSection::create([
            'target' => 'home', 'type' => 'speakers', 'order' => 0,
            'heading' => ['en' => 'Speakers'], 'settings' => ['limit' => 1],
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Pembicara Satu', $html);
        $this->assertStringNotContainsString('Pembicara Dua', $html);
    }

    /** Blok milik edisi lain tidak boleh bocor ke edisi berjalan. */
    public function test_blocks_from_another_edition_are_not_shown(): void
    {
        $other = Edition::create(['name' => 'ICOMAN 2027', 'is_active' => false]);
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 0, 'edition_id' => $other->id, 'heading' => ['en' => 'Old edition'], 'content' => ['en' => '<p>a</p>']]);
        PageSection::create(['target' => 'home', 'type' => 'rich_text', 'order' => 1, 'heading' => ['en' => 'Every edition'], 'content' => ['en' => '<p>b</p>']]);

        $this->get(route('home'))->assertOk()->assertSee('Every edition')->assertDontSee('Old edition');
    }

    public function test_blocks_can_be_added_to_a_custom_page(): void
    {
        Page::create(['slug' => 'sponsorship', 'title' => ['id' => 'Sponsor', 'en' => 'Sponsorship'], 'content' => ['id' => '<p>x</p>', 'en' => '<p>x</p>'], 'is_published' => true]);
        PageSection::create([
            'target' => 'page:sponsorship', 'type' => 'rich_text', 'order' => 0,
            'heading' => ['id' => 'Paket Sponsor', 'en' => 'Sponsor Packages'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
        ]);

        $this->get(route('page', ['slug' => 'sponsorship']))->assertOk()->assertSee('Sponsor Packages');
    }

    public function test_the_admin_can_load_the_built_in_layout_and_reorder_it(): void
    {
        $this->superadmin();

        Livewire::test(ListPageSections::class)
            ->assertOk()
            ->callAction(TestAction::make('installDefaults'));

        $this->assertSame(count(PageSection::HOME_DEFAULTS), PageSection::where('target', 'home')->count());
        // Tampilan website tidak boleh berubah begitu susunannya dimuat.
        $this->get(route('home'))->assertOk();

        $first = PageSection::where('target', 'home')->orderBy('order')->first();
        $this->assertSame('hero', $first->type);
    }

    /** Memuat susunan bawaan dua kali tidak boleh menggandakan bloknya. */
    public function test_loading_the_defaults_twice_changes_nothing(): void
    {
        PageSection::installDefaults('home');
        $count = PageSection::count();

        PageSection::installDefaults('home');

        $this->assertSame($count, PageSection::count());
    }

    public function test_reviewers_cannot_reach_the_page_builder(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('reviewer', 'web');
        $reviewer = User::create(['name' => 'Reviewer', 'email' => 'reviewer-blok@example.test', 'password' => 'secret-password']);
        $reviewer->assignRole('reviewer');
        $this->actingAs($reviewer, 'web');

        $this->assertFalse(PageSectionResource::canAccess());
    }

    // --- Halaman terstruktur lain (tahap 3) -------------------------------

    /** @return array<int, array{0: string, 1: string}> */
    public static function structuredPages(): array
    {
        return [
            ['speakers', 'speakers_full'],
            ['committee', 'committee_full'],
            ['faq', 'faq_full'],
            ['important-dates', 'dates_full'],
            ['schedule', 'schedule_full'],
            ['registration', 'registration_full'],
            ['call-for-papers', 'cfp_full'],
            ['downloads', 'downloads_full'],
        ];
    }

    #[DataProvider('structuredPages')]
    public function test_each_structured_page_has_a_built_in_block(string $target, string $type): void
    {
        $sections = PageSection::forTarget($target);

        $this->assertCount(1, $sections, 'Halaman '.$target.' tidak punya susunan bawaan.');
        $this->assertSame($type, $sections->first()->type);
    }

    public function test_a_block_can_be_added_above_a_structured_page(): void
    {
        PageSection::create([
            'target' => 'speakers', 'type' => 'rich_text', 'order' => 0,
            'heading' => ['id' => 'Catatan Panitia', 'en' => 'Committee Note'],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
        ]);
        PageSection::create(['target' => 'speakers', 'type' => 'speakers_full', 'order' => 1]);

        $this->speaker('Prof Nyata');
        $html = $this->get(route('speakers'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Prof Nyata'), strpos($html, 'Committee Note'));
    }

    /** Halaman terstruktur tetap tampil walau datanya masih kosong. */
    public function test_a_structured_page_without_data_still_renders(): void
    {
        foreach (['speakers', 'committee', 'faq', 'important-dates', 'schedule', 'registration'] as $target) {
            PageSection::forTarget($target);
        }

        $this->get(route('speakers'))->assertOk();
        $this->get(route('committee'))->assertOk();
        $this->get(route('faq'))->assertOk();
    }

    public function test_loading_the_defaults_for_one_page_leaves_the_others_alone(): void
    {
        PageSection::installDefaults('speakers');

        $this->assertSame(1, PageSection::where('target', 'speakers')->count());
        $this->assertSame(0, PageSection::where('target', 'home')->count());
        // Beranda tetap memakai bawaannya.
        $this->assertGreaterThan(5, PageSection::forTarget('home')->count());
    }

    /**
     * Daftar blok tanpa judul harus cocok dengan kenyataan di berkas tampilan:
     * kalau sebuah blok memakai $section->heading, isian judulnya tidak boleh
     * disembunyikan dari admin, dan sebaliknya.
     */
    public function test_the_headingless_list_matches_what_the_views_actually_use(): void
    {
        foreach (array_keys(PageSection::TYPES) as $type) {
            $markup = file_get_contents(resource_path('views/sections/'.$type.'.blade.php'));
            // Buang blok @php pembuka: di sana variabelnya hanya disiapkan.
            $body = preg_replace('/^@php.*?@endphp/s', '', $markup);

            $usesHeading = str_contains($body, '$heading') || str_contains($body, 'section->heading');
            $hidden = in_array($type, PageSection::HEADINGLESS_TYPES, true);

            $this->assertSame(
                ! $usesHeading,
                $hidden,
                $hidden
                    ? "Blok {$type} menyembunyikan isian judul padahal tampilannya memakai judul."
                    : "Blok {$type} menampilkan isian judul padahal tampilannya mengabaikannya.",
            );
        }
    }
}
