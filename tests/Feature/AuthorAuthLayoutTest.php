<?php

namespace Tests\Feature;

use App\Filament\Author\Auth\Login;
use App\Models\Edition;
use App\Models\ImportantDate;
use App\Settings\SiteSettings;
use Tests\TestCase;

/**
 * Halaman tamu portal author memakai kerangka split-screen: identitas
 * konferensi di kiri, formulir di kanan. Sebelumnya login memakai kartu polos
 * bawaan Filament sementara halaman daftar punya tampilannya sendiri, sehingga
 * keduanya terlihat seperti dua produk berbeda.
 */
class AuthorAuthLayoutTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        app(SiteSettings::class)->fill([
            'conference_name' => 'ICOMAN 2026',
            'event_location' => 'Makassar, Indonesia',
            'event_mode' => 'Hybrid',
        ])->save();

        $this->edition = Edition::create([
            'name' => 'ICOMAN 2026',
            'is_active' => true,
            'start_date' => now()->addMonths(2),
            'end_date' => now()->addMonths(2)->addDay(),
        ]);
    }

    private function abstractDeadline(\DateTimeInterface $closesAt): ImportantDate
    {
        return ImportantDate::create([
            'edition_id' => $this->edition->id,
            'label' => ['id' => 'Batas Abstrak', 'en' => 'Abstract Deadline'],
            'kind' => 'abstract',
            'date' => $closesAt,
            'closes_at' => $closesAt,
            'order' => 1,
        ]);
    }

    public function test_the_login_page_shows_the_conference_panel_beside_the_form(): void
    {
        app()->setLocale('en');

        $response = $this->get('/author/login')->assertOk();

        $response->assertSee('ICOMAN 2026');
        $response->assertSee('Makassar, Indonesia');
        $response->assertSee('Hybrid');
        // Formulirnya sendiri harus tetap ada.
        $response->assertSee('password', escape: false);
    }

    /** Identitas konferensi sudah besar di panel kiri; jangan diulang di atas form. */
    public function test_the_login_form_does_not_repeat_the_brand_heading(): void
    {
        $this->assertFalse(app(Login::class)->hasLogo());
    }

    /**
     * Rel kiri sengaja tenang: tanpa gambar hero dan tanpa hitung mundur, agar
     * perhatian jatuh ke formulir — mengikuti contoh desain yang diberikan.
     */
    public function test_the_panel_stays_quiet(): void
    {
        app(SiteSettings::class)->fill(['hero_image' => 'site/hero.jpg'])->save();
        $this->abstractDeadline(now()->addDays(10));

        $html = $this->get('/author/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('site/hero.jpg', $html, 'Gambar hero tidak dipakai lagi di halaman login.');
        $this->assertStringNotContainsString('x-countdown', $html);
        $this->assertStringNotContainsString('Abstract submission closes in', $html);
    }

    /** Kalimat bantuan berakhir dengan "di"/"at", jadi alamatnya harus ikut tercetak. */
    public function test_the_help_line_names_the_address(): void
    {
        app(SiteSettings::class)->fill(['contact_email' => 'panitia@example.test'])->save();

        $this->get('/author/login')
            ->assertOk()
            ->assertSee('panitia@example.test');
    }

    /** Halaman daftar memakai panel yang sama, bukan tampilannya sendiri. */
    public function test_the_register_page_uses_the_same_panel(): void
    {
        app()->setLocale('en');

        $this->get(route('author.register'))
            ->assertOk()
            ->assertSee('ICOMAN 2026')
            ->assertSee('Makassar, Indonesia');
    }

    /** Panel kiri tersembunyi di ponsel, jadi identitas acara diulang ringkas di atas form. */
    public function test_small_screens_still_get_the_conference_name(): void
    {
        $this->get('/author/login')
            ->assertOk()
            ->assertSee('lg:hidden', escape: false)
            ->assertSee('ICOMAN 2026');
    }

    /** Kerangka lama sudah dibuang; jangan sampai tertinggal separuh. */
    public function test_the_old_auth_shell_is_gone(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('.author-auth-shell', $css);
        $this->assertStringNotContainsString('.author-auth-story', $css);
        $this->assertStringNotContainsString('.author-auth-panel', $css);
    }
}
