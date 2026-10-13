<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Speaker;
use Tests\TestCase;

/**
 * The homepage speaker preview loops, while the full page remains a grid.
 */
class SpeakerCarouselTest extends TestCase
{
    public function test_homepage_speakers_render_in_a_looping_track_with_a_pause_control(): void
    {
        $this->speakers(5);

        $response = $this->get('/')->assertOk();

        $response->assertSee('speaker-marquee-track', false);
        $response->assertSee('data-speaker-marquee-toggle', false);
        $this->assertSame(2, substr_count($response->getContent(), 'speaker-marquee-set flex w-max'));
        for ($i = 1; $i <= 5; $i++) {
            $response->assertSee('Pembicara '.$i);
        }
    }

    public function test_a_single_speaker_is_static_without_a_pause_control(): void
    {
        $this->speakers(1);

        $this->get('/')->assertOk()
            ->assertSee('Pembicara 1')
            ->assertDontSee('speaker-marquee-track', false)
            ->assertDontSee('data-speaker-marquee-toggle', false);
    }

    public function test_homepage_marquee_includes_every_published_speaker(): void
    {
        $this->speakers(7);

        $response = $this->get('/')->assertOk();
        $response->assertSee('Pembicara 5');
        $response->assertSee('Pembicara 6');
        $response->assertSee('Pembicara 7');
    }

    public function test_the_full_speakers_page_also_uses_a_five_column_grid(): void
    {
        $this->speakers(7);

        $response = $this->get('/speakers')->assertOk();

        $response->assertDontSee('aria-roledescription="carousel"', false);
        $response->assertSee('lg:grid-cols-5', false);
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
