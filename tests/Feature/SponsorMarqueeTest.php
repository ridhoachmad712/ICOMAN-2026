<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\PageSection;
use App\Models\Sponsor;
use Tests\TestCase;

/**
 * Sponsor dan partner tampil sebagai satu pita berjalan.
 *
 * Sebelumnya mereka dipecah jadi lima kelompok bertumpuk menurut tingkatan
 * (Platinum, Gold, Silver, Partner, Media Partner), masing-masing dengan label
 * sendiri. Sekarang satu baris tanpa pemisahan, urut menurut nomor urut.
 */
class SponsorMarqueeTest extends TestCase
{
    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
    }

    private function sponsor(string $name, string $tier, int $order): Sponsor
    {
        return Sponsor::create([
            'edition_id' => $this->edition->id,
            'name' => $name,
            'tier' => $tier,
            'order' => $order,
            'is_published' => true,
        ]);
    }

    private function render(): string
    {
        PageSection::create(['target' => 'home', 'type' => 'sponsors', 'order' => 0]);

        return $this->get(route('home'))->assertOk()->getContent();
    }

    /** Label tingkatan tidak boleh muncul lagi. */
    public function test_the_tiers_are_no_longer_shown_as_separate_groups(): void
    {
        $this->sponsor('Bank Contoh', 'gold', 1);
        $this->sponsor('Mitra Media', 'media_partner', 2);

        $html = $this->render();

        foreach (['Platinum', 'Gold', 'Silver', 'Media Partner'] as $tierLabel) {
            $this->assertStringNotContainsString('>'.$tierLabel.'<', $html, 'Label tingkatan '.$tierLabel.' masih tampil.');
        }

        $this->assertStringContainsString('Bank Contoh', $html);
        $this->assertStringContainsString('Mitra Media', $html);
    }

    /** Urutannya murni nomor urut, bukan tingkatan. */
    public function test_the_order_follows_the_order_field_not_the_tier(): void
    {
        // Tingkatan "tertinggi" sengaja diberi nomor urut terakhir.
        $this->sponsor('Mitra Pertama', 'media_partner', 1);
        $this->sponsor('Sponsor Kedua', 'platinum', 2);

        $html = $this->render();

        $this->assertLessThan(
            strpos($html, 'Sponsor Kedua'),
            strpos($html, 'Mitra Pertama'),
            'Nomor urut harus menentukan urutan tampil, bukan tingkatannya.',
        );
    }

    public function test_the_row_moves_on_its_own(): void
    {
        $this->sponsor('Bank Contoh', 'gold', 1);

        $html = $this->render();

        $this->assertStringContainsString('sponsor-marquee-track', $html);
        $this->assertStringContainsString('animation-duration', $html);
    }

    /**
     * Pita berjalan butuh dua salinan agar sambungannya tak terlihat, tapi
     * pembaca layar tidak boleh mendengar daftarnya dua kali.
     */
    public function test_the_duplicate_copy_is_hidden_from_screen_readers(): void
    {
        $this->sponsor('Bank Contoh', 'gold', 1);

        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'sponsor-marquee-set'));
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    /** Sponsor yang belum dikonfirmasi tidak boleh bocor ke halaman publik. */
    public function test_unpublished_sponsors_stay_hidden(): void
    {
        $this->sponsor('Sudah Pasti', 'gold', 1);
        Sponsor::create([
            'edition_id' => $this->edition->id,
            'name' => 'Masih Rahasia',
            'tier' => 'gold',
            'order' => 2,
            'is_published' => false,
        ]);

        $html = $this->render();

        $this->assertStringContainsString('Sudah Pasti', $html);
        $this->assertStringNotContainsString('Masih Rahasia', $html);
    }
}
