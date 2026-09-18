<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Speaker;
use Tests\TestCase;

/**
 * Section speakers menampilkan lima pembicara langsung dalam satu grid.
 */
class SpeakerCarouselTest extends TestCase
{
    public function test_five_speakers_render_together_without_a_carousel(): void
    {
        $this->speakers(5);

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('aria-roledescription="carousel"', false);
        $response->assertSee('xl:grid-cols-5', false);
        for ($i = 1; $i <= 5; $i++) {
            $response->assertSee('Pembicara '.$i);
        }
    }

    public function test_homepage_limits_the_preview_to_five_speakers(): void
    {
        $this->speakers(7);

        $response = $this->get('/')->assertOk();
        $response->assertSee('Pembicara 5');
        $response->assertDontSee('Pembicara 6');
        $response->assertDontSee('Pembicara 7');
    }

    public function test_the_full_speakers_page_also_uses_a_five_column_grid(): void
    {
        $this->speakers(7);

        $response = $this->get('/speakers')->assertOk();

        $response->assertDontSee('aria-roledescription="carousel"', false);
        $response->assertSee('xl:grid-cols-5', false);
        $response->assertSee('Pembicara 7');
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
