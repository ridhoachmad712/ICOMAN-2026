<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Speaker;
use Tests\TestCase;

/**
 * Section speakers di halaman depan berbentuk carousel: semua pembicara setara
 * (tanpa kartu spotlight terpisah), 4 kartu per tampilan, bergeser otomatis.
 */
class SpeakerCarouselTest extends TestCase
{
    public function test_more_than_four_speakers_render_as_a_carousel(): void
    {
        $this->speakers(7);

        $response = $this->get('/')->assertOk();

        $response->assertSee('aria-roledescription="carousel"', false);
        // Autoplay + jeda saat disentuh.
        $response->assertSee('paused = true', false);
        $response->assertSee('setInterval', false);
        // Kendali manual tetap ada untuk mouse & keyboard.
        $response->assertSee('Next speakers', false);
        $response->assertSee('Previous speakers', false);
    }

    public function test_all_speakers_sit_in_the_carousel_without_a_separate_spotlight(): void
    {
        $this->speakers(7);

        $html = $this->get('/')->assertOk()->getContent();

        // Ketujuh nama muncul di dalam satu track carousel.
        for ($i = 1; $i <= 7; $i++) {
            $this->assertStringContainsString('Pembicara '.$i, $html);
        }

        // Penanda kartu spotlight lama (grid 3 kolom berisi bio) sudah tidak ada.
        $this->assertStringNotContainsString('sm:grid-cols-3 items-center card', $html);
    }

    /** Dengan 4 pembicara atau kurang tidak ada yang bisa digeser — tampilkan grid. */
    public function test_four_or_fewer_speakers_fall_back_to_a_plain_grid(): void
    {
        $this->speakers(4);

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('aria-roledescription="carousel"', false);
        $response->assertSee('lg:grid-cols-4', false);
    }

    public function test_speakers_that_are_still_tba_keep_the_announcement_placeholder(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        Speaker::create([
            'edition_id' => $edition->id, 'name' => 'Keynote Speaker 1 (TBA)',
            'type' => 'keynote', 'order' => 1, 'is_published' => true,
        ]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('To Be Announced');
        $response->assertDontSee('aria-roledescription="carousel"', false);
    }

    private function speakers(int $count): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        for ($i = 1; $i <= $count; $i++) {
            Speaker::create([
                'edition_id' => $edition->id,
                'name' => 'Pembicara '.$i,
                'affiliation' => 'Universitas Contoh',
                'type' => $i === 1 ? 'keynote' : 'invited',
                'order' => $i,
                'is_published' => true,
            ]);
        }
    }
}
