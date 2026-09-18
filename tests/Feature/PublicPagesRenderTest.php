<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\RegistrationFee;
use Tests\TestCase;

/**
 * Tidak ada halaman publik yang boleh membalas 5xx. 404 masih sah (mis. halaman
 * CMS yang belum dibuat), tapi error server berarti bug.
 */
class PublicPagesRenderTest extends TestCase
{
    public function test_no_public_page_returns_a_server_error(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $paths = [
            '/', '/committee', '/speakers', '/call-for-papers', '/important-dates',
            '/registration', '/author-guidelines', '/program', '/faq', '/contact',
            '/news', '/privacy', '/sitemap.xml',
            '/about', '/venue', '/p/tidak-ada',
            // Template naskah boleh belum tersedia, tapi harus 404 - bukan 500.
            '/author-guidelines/manuscript-template',
        ];

        $failures = [];
        foreach ($paths as $path) {
            try {
                $status = $this->get($path)->getStatusCode();
            } catch (\Throwable $e) {
                $status = 500;
            }
            if ($status >= 500) {
                $failures[] = $path.' => '.$status;
            }
        }

        $this->assertSame([], $failures, "Halaman publik membalas error server:\n".implode("\n", $failures));
    }

    /**
     * Halaman privasi menyebut siapa yang menerima data pembayaran peserta.
     * Nama itu ikut berubah setiap kali gateway-nya berganti, dan sempat
     * tertinggal dua kali. Gateway lama tidak boleh lagi disebut di sana.
     */
    public function test_the_privacy_page_names_the_payment_processor_we_actually_use(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('BorderPay');
        $response->assertDontSee('Midtrans');
        $response->assertDontSee('Kasera');
    }

    public function test_the_missing_manuscript_template_is_a_404_not_a_crash(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $this->assertNull(manuscriptTemplatePath(), 'Tes ini mengasumsikan template belum disediakan.');
        $this->get('/author-guidelines/manuscript-template')->assertNotFound();
    }

    public function test_closed_abstract_calls_to_action_lead_registered_authors_to_the_portal(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        ImportantDate::create([
            'edition_id' => $edition->id,
            'kind' => 'abstract',
            'label' => ['en' => 'Abstract deadline', 'id' => 'Batas abstrak'],
            'date' => now()->subDay(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee(__('site.public_submission_closed'))
            ->assertSee(route('filament.author.pages.author-dashboard'), false)
            ->assertDontSee(route('author.register.terms', ['role' => 'presenter']), false);

        $this->get('/call-for-papers')
            ->assertOk()
            ->assertSee(__('site.public_submission_closed'))
            ->assertSee(route('filament.author.pages.author-dashboard'), false);
    }

    public function test_registration_shows_prices_before_the_long_participation_explanation(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        RegistrationFee::create([
            'edition_id' => $edition->id,
            'category' => ['en' => 'Presenter', 'id' => 'Pemakalah'],
            'audience' => 'presenter',
            'registrant_category' => 'general',
            'price_regular' => 400_000,
            'currency' => 'IDR',
        ]);

        $html = $this->get('/registration')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, __('site.reg_will_you_present_a_paper')),
            strpos($html, 'IDR 400.000'),
        );
    }

    public function test_missing_manuscript_template_is_explained_on_the_guidelines_page(): void
    {
        Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);

        $this->get('/author-guidelines')
            ->assertOk()
            ->assertSee(__('site.public_template_pending'))
            ->assertDontSee(__('site.guide_download_manuscript_template_docx'));
    }

    public function test_important_dates_are_chronological_and_undated_items_come_last(): void
    {
        $edition = Edition::create(['name' => 'ICOMAN 2026', 'is_active' => true]);
        foreach ([
            ['label' => 'Later', 'date' => now()->addDays(5)],
            ['label' => 'TBA', 'date' => null],
            ['label' => 'Sooner', 'date' => now()->addDay()],
        ] as $date) {
            ImportantDate::create([
                'edition_id' => $edition->id,
                'label' => ['en' => $date['label']],
                'date' => $date['date'],
            ]);
        }

        $html = $this->get('/important-dates')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Later'), strpos($html, 'Sooner'));
        $this->assertLessThan(strpos($html, 'TBA'), strpos($html, 'Later'));
    }
}
