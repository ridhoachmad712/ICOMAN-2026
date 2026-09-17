<?php

namespace Tests\Feature;

use App\Models\Edition;
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
}
