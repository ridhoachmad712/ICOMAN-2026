<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Halaman privasi masih menyebut Midtrans sebagai pemroses pembayaran, padahal
 * gateway-nya sudah dua kali berganti. Keliru menyebut pihak yang menerima data
 * peserta bukan urusan kosmetik: kalimat itulah dasar hukum pengirimannya.
 *
 * Yang diganti hanya nama pemrosesnya, bukan kalimatnya, supaya suntingan admin
 * atas teks lain di halaman itu tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('Midtrans', 'BorderPay');
    }

    public function down(): void
    {
        $this->rename('BorderPay', 'Midtrans');
    }

    private function rename(string $from, string $to): void
    {
        $page = Page::where('slug', 'privacy')->first();

        if (! $page) {
            return;
        }

        $changed = false;

        foreach ($page->getTranslations('content') as $locale => $html) {
            if (! is_string($html) || ! str_contains($html, $from)) {
                continue;
            }

            $page->setTranslation('content', $locale, str_replace($from, $to, $html));
            $changed = true;
        }

        if ($changed) {
            $page->save();
        }
    }
};
