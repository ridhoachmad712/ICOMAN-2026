<?php

namespace Tests\Feature;

use App\Livewire\PageEditor;
use App\Models\Author;
use App\Models\Edition;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Penyuntingan langsung di atas halaman publik (?edit=1).
 *
 * Yang dijaga di sini bukan sekadar "bisa jalan", tapi bahwa perkakasnya tidak
 * pernah sampai ke tangan pengunjung biasa, dan setiap aksinya memeriksa ulang
 * kewenangan — bukan hanya tampilannya yang disembunyikan.
 */
class OnPageEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function editor(string $role = 'superadmin', string $email = 'penyunting@example.test'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::create(['name' => 'Penyunting', 'email' => $email, 'password' => 'secret-password']);
        $user->assignRole($role);
        $this->actingAs($user, 'web');

        return $user;
    }

    private function block(int $order = 0, string $heading = 'Judul Blok'): PageSection
    {
        return PageSection::create([
            'target' => 'home',
            'type' => 'rich_text',
            'order' => $order,
            'heading' => ['id' => $heading, 'en' => $heading],
            'content' => ['id' => '<p>Isi.</p>', 'en' => '<p>Body.</p>'],
        ]);
    }

    // --- Siapa yang melihat perkakasnya -------------------------------------

    public function test_a_visitor_sees_no_trace_of_the_editor(): void
    {
        $this->block();

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('ps-edit-toolbar', $html);
        $this->assertStringNotContainsString('Mode sunting halaman', $html);
        $this->assertStringNotContainsString('Sunting halaman', $html);
    }

    /** Author yang login di portalnya bukan penyunting website. */
    public function test_an_author_account_cannot_open_edit_mode(): void
    {
        $this->block();
        $author = Author::create([
            'name' => 'Penulis', 'email' => 'penulis@example.test', 'password' => 'secret-password',
            'participation_type' => 'presenter', 'registrant_category' => 'general',
        ]);
        $this->actingAs($author, 'author');

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('ps-edit-toolbar', $html);
    }

    public function test_a_reviewer_cannot_open_edit_mode(): void
    {
        $this->block();
        $this->editor('reviewer', 'reviewer-sunting@example.test');

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('ps-edit-toolbar', $html);
    }

    public function test_an_editor_gets_the_toolbar_and_the_add_buttons(): void
    {
        $this->editor();
        $this->block();

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('ps-edit-toolbar', $html);
        $this->assertStringContainsString('Mode sunting halaman', $html);
        $this->assertStringContainsString('Tambah blok', $html);
    }

    /** Tanpa ?edit=1 halaman tetap bersih, bahkan untuk penyunting. */
    public function test_the_page_stays_clean_until_edit_mode_is_asked_for(): void
    {
        $this->editor();
        $this->block();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('ps-edit-toolbar', $html);
        // Tapi pintu masuknya ditawarkan.
        $this->assertStringContainsString('Sunting halaman', $html);
    }

    public function test_a_content_admin_may_edit_too(): void
    {
        $this->editor('content_admin', 'konten@example.test');
        $this->block();

        $this->get(route('home', ['edit' => 1]))->assertOk()->assertSee('ps-edit-toolbar', escape: false);
    }

    // --- Aksi penyuntingan --------------------------------------------------

    public function test_moving_a_block_changes_the_order(): void
    {
        $this->editor();
        $first = $this->block(0, 'Blok Pertama');
        $second = $this->block(1, 'Blok Kedua');

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('move', $second->id, -1);

        $this->assertSame(0, $second->refresh()->order);
        $this->assertSame(1, $first->refresh()->order);
    }

    public function test_hiding_and_showing_a_block(): void
    {
        $this->editor();
        $block = $this->block();

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('toggleVisibility', $block->id);

        $this->assertFalse($block->refresh()->is_published);
    }

    public function test_duplicating_a_block_puts_the_copy_right_after_it(): void
    {
        $this->editor();
        $block = $this->block(0, 'Asli');
        $after = $this->block(1, 'Sesudahnya');

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('duplicate', $block->id);

        $copy = PageSection::where('target', 'home')->where('id', '!=', $block->id)->orderBy('order')->first();

        $this->assertSame('Asli', $copy->getTranslation('heading', 'id'));
        $this->assertSame(1, $copy->order);
        $this->assertSame(2, $after->refresh()->order, 'Blok sesudahnya harus bergeser turun.');
    }

    public function test_deleting_a_block(): void
    {
        $this->editor();
        $block = $this->block();

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('remove', $block->id);

        $this->assertDatabaseMissing('page_sections', ['id' => $block->id]);
    }

    public function test_adding_a_block_after_another_one(): void
    {
        $this->editor();
        $block = $this->block(0);

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('add', 'quote', $block->id);

        $added = PageSection::where('target', 'home')->where('type', 'quote')->first();

        $this->assertNotNull($added);
        $this->assertSame(1, $added->order);
    }

    /** Jenis blok karangan sendiri tidak boleh bisa dibuat. */
    public function test_an_unknown_block_type_is_refused(): void
    {
        $this->editor();

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('add', 'blok-karangan', null)
            ->assertStatus(422);
    }

    public function test_editing_a_heading_in_place_saves_it(): void
    {
        $this->editor();
        app()->setLocale('id');
        $block = $this->block(0, 'Judul Lama');

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('updateText', $block->id, 'heading', '  Judul Baru  ');

        $block->refresh();

        $this->assertSame('Judul Baru', $block->getTranslation('heading', 'id'));
        // Bahasa lain tidak ikut berubah.
        $this->assertSame('Judul Lama', $block->getTranslation('heading', 'en'));
    }

    /** Isian yang tidak ada dalam daftar tidak boleh ditulis lewat jalur ini. */
    public function test_only_the_listed_text_fields_can_be_written(): void
    {
        $this->editor();
        $block = $this->block();

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('updateText', $block->id, 'target', 'page:diretas')
            ->assertStatus(422);

        $this->assertSame('home', $block->refresh()->target);
    }

    /** HTML yang ditempel ke teks judul dibersihkan di server. */
    public function test_pasted_markup_is_stripped(): void
    {
        $this->editor();
        app()->setLocale('id');
        $block = $this->block();

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('updateText', $block->id, 'heading', '<script>alert(1)</script>Judul');

        $this->assertSame('alert(1)Judul', $block->refresh()->getTranslation('heading', 'id'));
    }

    // --- Kewenangan pada setiap aksi ----------------------------------------

    /**
     * Menyembunyikan tombolnya saja tidak cukup: komponen Livewire menerima
     * permintaan langsung dari peramban, jadi tiap aksi memeriksa ulang.
     */
    public function test_every_action_rechecks_authorisation(): void
    {
        $this->editor();
        $block = $this->block();
        $component = Livewire::test(PageEditor::class, ['target' => 'home']);

        // Kewenangan dicabut setelah komponennya terlanjur terpasang.
        auth('web')->logout();

        $component->call('remove', $block->id)->assertForbidden();
        $this->assertDatabaseHas('page_sections', ['id' => $block->id]);
    }

    public function test_mounting_the_editor_without_permission_is_refused(): void
    {
        Livewire::test(PageEditor::class, ['target' => 'home'])->assertForbidden();
    }

    /** Blok milik halaman lain tidak bisa disentuh lewat editor halaman ini. */
    public function test_a_block_from_another_page_cannot_be_touched(): void
    {
        $this->editor();
        $other = PageSection::create([
            'target' => 'speakers', 'type' => 'rich_text', 'order' => 0,
            'heading' => ['id' => 'Milik Halaman Lain', 'en' => 'Another Page'],
        ]);

        // Editor halaman ini hanya mencari di antara blok miliknya sendiri, jadi
        // blok halaman lain tidak ditemukan sama sekali.
        try {
            Livewire::test(PageEditor::class, ['target' => 'home'])->call('remove', $other->id);
            $this->fail('Blok halaman lain seharusnya tidak bisa disentuh.');
        } catch (ModelNotFoundException) {
            // Inilah yang diharapkan.
        }

        $this->assertDatabaseHas('page_sections', ['id' => $other->id]);
    }

    /** Halaman yang masih memakai susunan bawaan disalin dulu agar bisa diubah. */
    public function test_the_built_in_layout_is_copied_in_before_the_first_change(): void
    {
        $this->editor();
        $this->assertTrue(PageSection::where('target', 'home')->doesntExist());

        Livewire::test(PageEditor::class, ['target' => 'home'])->call('move', 1, 1);

        $this->assertGreaterThan(5, PageSection::where('target', 'home')->count());
    }

    // --- Sambungan ke peramban ---------------------------------------------

    /**
     * Toolbar berada DI LUAR akar komponen Livewire, jadi wire:click di sana
     * tidak akan pernah sampai — tombolnya akan diam saja di peramban. Aksinya
     * harus dikirim sebagai event Livewire.
     */
    public function test_the_toolbar_does_not_rely_on_wire_click(): void
    {
        $this->editor();
        $this->block();

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $toolbar = substr($html, strpos($html, 'ps-edit-toolbar'));
        $toolbar = substr($toolbar, 0, strpos($toolbar, '</div>'));

        $this->assertStringNotContainsString('wire:click', $toolbar);
        $this->assertStringContainsString("Livewire.dispatch('ps-move'", $toolbar);
    }

    /**
     * Alpine hanya menyalakan direktif di dalam cakupan x-data. Tanpa itu,
     * seluruh x-on:click pada toolbar dan tombol tambah tidak pernah terpasang.
     */
    public function test_the_edit_markup_sits_inside_an_alpine_scope(): void
    {
        $this->editor();
        $this->block();

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $block = substr($html, strpos($html, 'ps-block'));
        $block = substr($block, 0, strpos($block, 'ps-edit-toolbar'));

        $this->assertStringContainsString('x-data', $block);
        $this->assertStringContainsString('draggable="true"', $block);
    }

    /** Teks yang disunting langsung juga di luar akar komponen. */
    public function test_inline_text_editing_uses_an_event_too(): void
    {
        $this->editor();
        PageSection::create([
            'target' => 'home', 'type' => 'heading', 'order' => 0,
            'heading' => ['id' => 'Judul', 'en' => 'Heading'],
        ]);

        $html = $this->get(route('home', ['edit' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('contenteditable="true"', $html);
        $this->assertStringContainsString("Livewire.dispatch('ps-text'", $html);
        $this->assertStringNotContainsString('$wire.updateText', $html);
    }

    public function test_dragging_a_block_onto_another_moves_it_there(): void
    {
        $this->editor();
        $first = $this->block(0, 'Pertama');
        $second = $this->block(1, 'Kedua');
        $third = $this->block(2, 'Ketiga');

        // Blok ketiga diseret ke posisi pertama.
        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('dropOn', $third->id, $first->id);

        $this->assertSame(0, $third->refresh()->order);
        $this->assertSame(1, $first->refresh()->order);
        $this->assertSame(2, $second->refresh()->order);
    }

    public function test_dropping_a_block_onto_itself_changes_nothing(): void
    {
        $this->editor();
        $first = $this->block(0, 'Pertama');
        $second = $this->block(1, 'Kedua');

        Livewire::test(PageEditor::class, ['target' => 'home'])
            ->call('dropOn', $first->id, $first->id);

        $this->assertSame(0, $first->refresh()->order);
        $this->assertSame(1, $second->refresh()->order);
    }

    public function test_dragging_also_rechecks_authorisation(): void
    {
        $this->editor();
        $first = $this->block(0, 'Pertama');
        $second = $this->block(1, 'Kedua');
        $component = Livewire::test(PageEditor::class, ['target' => 'home']);

        auth('web')->logout();

        $component->call('dropOn', $second->id, $first->id)->assertForbidden();
        $this->assertSame(1, $second->refresh()->order);
    }
}
