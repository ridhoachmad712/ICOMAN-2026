<?php

namespace Tests\Feature;

use App\Settings\SiteSettings;
use Tests\TestCase;

/**
 * Satu produk, satu identitas warna.
 *
 * Tiap tempat dulu memilih warna cadangannya sendiri: situs publik jatuh ke
 * biru `#1d4ed8`, portal author ke jingga `#d9621c`. Selama Pengaturan terisi
 * keduanya tampak sama dan selisih itu tersembunyi, jadi tes ini justru
 * memeriksa keadaan yang menyingkapnya: Pengaturan yang dikosongkan.
 */
class BrandIdentityTest extends TestCase
{
    private function settings(?string $brand, ?string $brand2): SiteSettings
    {
        $settings = app(SiteSettings::class);
        $settings->primary_color = $brand;
        $settings->secondary_color = $brand2;
        $settings->save();

        return $settings;
    }

    public function test_an_empty_setting_falls_back_to_one_shared_colour(): void
    {
        $settings = $this->settings(null, null);

        $this->assertSame(SiteSettings::DEFAULT_BRAND, $settings->brandColor());
        $this->assertSame(SiteSettings::DEFAULT_BRAND_2, $settings->brandColor2());
    }

    public function test_a_stored_colour_wins_over_the_fallback(): void
    {
        $settings = $this->settings('#123456', '#654321');

        $this->assertSame('#123456', $settings->brandColor());
        $this->assertSame('#654321', $settings->brandColor2());
    }

    /**
     * Inti aturannya: dengan Pengaturan kosong, situs publik dan portal author
     * harus memakai warna yang sama persis.
     */
    public function test_the_public_site_and_the_author_portal_agree_when_nothing_is_set(): void
    {
        $this->settings(null, null);

        $public = $this->get(route('home'))->assertOk()->getContent();
        $portal = $this->get(route('author.register'))->assertOk()->getContent();

        foreach ([SiteSettings::DEFAULT_BRAND, SiteSettings::DEFAULT_BRAND_2] as $colour) {
            $this->assertStringContainsString($colour, $public, 'Situs publik tidak memakai warna bersama.');
            $this->assertStringContainsString($colour, $portal, 'Portal author tidak memakai warna bersama.');
        }
    }

    /** Dan tetap sepakat ketika panitia menetapkan warnanya sendiri. */
    public function test_they_still_agree_on_a_chosen_colour(): void
    {
        $this->settings('#2f6f4e', '#102820');

        $public = $this->get(route('home'))->assertOk()->getContent();
        $portal = $this->get(route('author.register'))->assertOk()->getContent();

        foreach (['#2f6f4e', '#102820'] as $colour) {
            $this->assertStringContainsString($colour, $public);
            $this->assertStringContainsString($colour, $portal);
        }
    }

    /** Warna cadangan yang lama tidak boleh tertinggal di mana pun. */
    public function test_no_stray_fallback_colour_remains_in_the_code(): void
    {
        $abandoned = ['#1d4ed8', '#0f172a', '#18315e'];

        foreach (['resources/views/components/layout.blade.php', 'resources/views/components/author-layout.blade.php'] as $file) {
            $source = file_get_contents(base_path($file));

            foreach ($abandoned as $colour) {
                $this->assertStringNotContainsString(
                    $colour,
                    $source,
                    $file.' masih memuat warna cadangannya sendiri ('.$colour.').',
                );
            }
        }
    }
}
