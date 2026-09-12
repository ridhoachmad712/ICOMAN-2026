<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\ManageSiteTexts;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteText;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kustomisasi website tahap 1: seluruh label bisa disunting admin, susunan menu
 * pindah ke database, dan kebijakan privasi jadi halaman CMS.
 */
class SiteCustomizationTest extends TestCase
{
    private function superadmin(string $email = 'super-kustom@example.test'): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('superadmin', 'web');
        $user = User::create(['name' => 'Super', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole('superadmin');
        $this->actingAs($user, 'web');

        return $user;
    }

    // --- Teks website -----------------------------------------------------

    public function test_an_edit_replaces_the_built_in_label(): void
    {
        app()->setLocale('en');
        $this->assertSame('Read more', __('site.read_more'));

        SiteText::create(['key' => 'site.read_more', 'value' => ['en' => 'Keep reading']]);

        $this->assertSame('Keep reading', $this->freshTranslation('site.read_more', 'en'));
    }

    /** Menghapus suntingan mengembalikan teks aslinya, bukan mengosongkan halaman. */
    public function test_removing_an_edit_restores_the_default(): void
    {
        $text = SiteText::create(['key' => 'site.read_more', 'value' => ['en' => 'Keep reading']]);
        $this->assertSame('Keep reading', $this->freshTranslation('site.read_more', 'en'));

        $text->delete();

        $this->assertSame('Read more', $this->freshTranslation('site.read_more', 'en'));
    }

    /** Satu bahasa boleh disunting tanpa memaksa bahasa lain ikut diisi. */
    public function test_each_language_falls_back_on_its_own(): void
    {
        SiteText::create(['key' => 'site.read_more', 'value' => ['id' => 'Baca terus']]);

        $this->assertSame('Baca terus', $this->freshTranslation('site.read_more', 'id'));
        $this->assertSame('Read more', $this->freshTranslation('site.read_more', 'en'));
    }

    /** Kunci di luar grup yang dikelola tidak boleh ikut tertimpa. */
    public function test_unrelated_translation_groups_are_untouched(): void
    {
        SiteText::create(['key' => 'validation.required', 'value' => ['en' => 'DISUNTING']]);

        $this->assertNotSame('DISUNTING', $this->freshTranslation('validation.required', 'en'));
    }

    public function test_the_admin_page_lists_the_keys_and_saves_an_edit(): void
    {
        $this->superadmin();

        $this->assertContains('read_more', ManageSiteTexts::keysFor('site'));

        Livewire::test(ManageSiteTexts::class)
            ->assertOk()
            ->set('data.site__read_more__en', 'Keep reading')
            ->call('save');

        $this->assertDatabaseHas('site_texts', ['key' => 'site.read_more']);
        $this->assertSame('Keep reading', $this->freshTranslation('site.read_more', 'en'));
    }

    /** Mengetik ulang teks bawaan bukan suntingan — jangan disimpan. */
    public function test_typing_the_default_value_stores_nothing(): void
    {
        $this->superadmin();

        Livewire::test(ManageSiteTexts::class)
            ->set('data.site__read_more__en', 'Read more')
            ->call('save');

        $this->assertDatabaseMissing('site_texts', ['key' => 'site.read_more']);
    }

    public function test_only_superadmins_may_edit_website_texts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('content_admin', 'web');
        $editor = User::create(['name' => 'Editor', 'email' => 'editor-teks@example.test', 'password' => 'secret-password']);
        $editor->assignRole('content_admin');
        $this->actingAs($editor, 'web');

        $this->assertFalse(ManageSiteTexts::canAccess());
    }

    // --- Menu navigasi ----------------------------------------------------

    public function test_the_menu_falls_back_to_the_built_in_layout_while_empty(): void
    {
        $this->assertTrue(MenuItem::query()->doesntExist());
        $this->assertGreaterThan(0, MenuItem::forNavigation()->count());
    }

    public function test_an_admin_menu_replaces_the_built_in_one(): void
    {
        MenuItem::create(['label' => ['id' => 'Beranda', 'en' => 'Home'], 'type' => 'route', 'route_name' => 'home', 'order' => 0]);

        $tree = MenuItem::forNavigation();

        $this->assertCount(1, $tree);
        $this->assertSame(route('home'), $tree->first()['url']);
    }

    public function test_a_custom_page_can_be_put_in_the_menu(): void
    {
        $page = Page::create(['slug' => 'sponsorship', 'title' => ['id' => 'Sponsor', 'en' => 'Sponsorship'], 'content' => ['id' => 'x', 'en' => 'x'], 'is_published' => true]);
        MenuItem::create(['label' => ['id' => 'Sponsor', 'en' => 'Sponsorship'], 'type' => 'page', 'page_id' => $page->id, 'order' => 0]);

        $this->assertSame(route('page', ['slug' => 'sponsorship']), MenuItem::forNavigation()->first()['url']);
    }

    /** Halaman yang di-unpublish tidak boleh menyisakan tautan mati di menu. */
    public function test_a_menu_item_pointing_at_an_unpublished_page_disappears(): void
    {
        $page = Page::create(['slug' => 'draft', 'title' => ['id' => 'Draf', 'en' => 'Draft'], 'content' => ['id' => 'x', 'en' => 'x'], 'is_published' => false]);
        MenuItem::create(['label' => ['id' => 'Draf', 'en' => 'Draft'], 'type' => 'page', 'page_id' => $page->id, 'order' => 0]);
        MenuItem::create(['label' => ['id' => 'Beranda', 'en' => 'Home'], 'type' => 'route', 'route_name' => 'home', 'order' => 1]);

        $tree = MenuItem::forNavigation();

        $this->assertCount(1, $tree);
        $this->assertSame(route('home'), $tree->first()['url']);
    }

    public function test_submenu_items_are_nested_under_their_parent(): void
    {
        $parent = MenuItem::create(['label' => ['id' => 'Tentang', 'en' => 'About'], 'type' => 'route', 'order' => 0]);
        MenuItem::create(['parent_id' => $parent->id, 'label' => ['id' => 'Komite', 'en' => 'Committee'], 'type' => 'route', 'route_name' => 'committee', 'order' => 0]);

        $tree = MenuItem::forNavigation();

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree->first()['children']);
        $this->assertSame(route('committee'), $tree->first()['children'][0]['url']);
    }

    /** Butir tersembunyi tidak boleh tampil di website. */
    public function test_unpublished_items_are_left_out(): void
    {
        MenuItem::create(['label' => ['id' => 'Beranda', 'en' => 'Home'], 'type' => 'route', 'route_name' => 'home', 'order' => 0]);
        MenuItem::create(['label' => ['id' => 'Rahasia', 'en' => 'Hidden'], 'type' => 'route', 'route_name' => 'contact', 'is_published' => false, 'order' => 1]);

        $this->assertCount(1, MenuItem::forNavigation());
    }

    public function test_the_admin_can_load_the_default_menu(): void
    {
        $this->superadmin('super-menu@example.test');

        Livewire::test(ListMenuItems::class)
            ->assertOk()
            ->callAction(TestAction::make('installDefaults'));

        $this->assertGreaterThan(5, MenuItem::count());
        $this->assertTrue(MenuItem::whereNotNull('parent_id')->exists());
    }

    public function test_the_navbar_shows_menu_items_from_the_database(): void
    {
        MenuItem::create(['label' => ['id' => 'Sponsor Kami', 'en' => 'Our Sponsors'], 'type' => 'url', 'url' => 'https://example.test/sponsors', 'order' => 0]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Our Sponsors')
            ->assertSee('https://example.test/sponsors');
    }

    // --- Kebijakan privasi ------------------------------------------------

    public function test_the_privacy_page_is_now_editable_content(): void
    {
        $page = Page::where('slug', 'privacy')->first();

        $this->assertNotNull($page, 'Halaman privasi belum dipindahkan ke CMS.');
        $this->assertTrue($page->is_published);

        $page->setTranslation('content', 'en', '<p>Our own wording.</p>')->save();

        $this->get('/privacy')->assertOk()->assertSee('Our own wording.', escape: false);
    }

    /** Membaca terjemahan seperti permintaan baru — penerjemah menyimpan grup di memori. */
    private function freshTranslation(string $key, string $locale): string
    {
        app()->forgetInstance('translator');
        app()->forgetInstance('translation.loader');
        Lang::clearResolvedInstances();

        return trans($key, [], $locale);
    }
}
