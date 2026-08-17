<?php

namespace Database\Seeders;

use App\Models\Edition;
use App\Models\Faq;
use App\Models\ImportantDate;
use App\Models\News;
use App\Models\Page;
use App\Models\RegistrationFee;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\Topic;
use App\Settings\SiteSettings;
use Illuminate\Database\Seeder;

/**
 * ⚠️ DEV ONLY — data contoh untuk preview frontend lokal.
 * Bukan data asli. Hapus sebelum production:
 *   php artisan db:wipe && php artisan migrate --seed
 * atau jalankan Edition/RoleSeeder saja.
 *
 * Cara pakai: php artisan db:seed --class=DevSeeder
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        $edition = Edition::firstOrCreate(['name' => 'ICOMAN 2026'], ['is_active' => true]);
        $edition->update([
            'theme' => ['en' => 'Advancing Sustainable Management in the Digital Era', 'id' => 'Memajukan Manajemen Berkelanjutan di Era Digital'],
            'start_date' => now()->addMonths(4)->startOfDay(),
            'end_date' => now()->addMonths(4)->addDays(2)->startOfDay(),
            'is_active' => true,
        ]);
        $eid = $edition->id;

        // Site settings contact/theme (dev)
        $s = app(SiteSettings::class);
        $s->contact_email = 'secretariat@icoman.example';
        $s->contact_whatsapp = '+62 812-0000-0000';
        $s->contact_address = 'Faculty of Economics, Universitas Contoh, Makassar, Indonesia';
        $s->social_instagram = 'https://instagram.com/icoman';
        $s->event_location = 'Makassar, Indonesia';
        $s->event_mode = 'Hybrid (Onsite & Online)';
        $s->organizer_name = 'Faculty of Economics & Business, Universitas Contoh';
        $s->save();

        // Nama = nama orang saja; gelar terpisah di title_degree (hindari duplikasi tampilan).
        $speakers = [
            ['keynote', 'Prof. Dr.', 'Amelia Hartono', 'National University of Singapore', 'SG', ['en' => 'Sustainable Corporate Governance', 'id' => 'Tata Kelola Korporat Berkelanjutan']],
            ['keynote', 'Prof.', 'John Meyer', 'University of Melbourne', 'AU', ['en' => 'Digital Leadership', 'id' => 'Kepemimpinan Digital']],
            ['invited', 'Dr.', 'Siti Rahmawati', 'Universitas Indonesia', 'ID', ['en' => 'Green Supply Chain', 'id' => 'Rantai Pasok Hijau']],
            ['invited', 'Dr.', 'Kenji Tanaka', 'Kyoto University', 'JP', ['en' => 'Behavioral Finance', 'id' => 'Keuangan Perilaku']],
        ];
        Speaker::where('edition_id', $eid)->delete(); // reset agar tidak dobel saat re-seed dev
        foreach ($speakers as $i => [$type, $degree, $name, $aff, $country, $topic]) {
            Speaker::create([
                'edition_id' => $eid, 'type' => $type, 'title_degree' => $degree, 'name' => $name,
                'affiliation' => $aff, 'country' => $country, 'topic' => $topic, 'order' => $i,
            ]);
        }

        $dates = [
            [['en' => 'Full Paper Submission Deadline', 'id' => 'Batas Pengumpulan Paper'], now()->addMonths(2), true],
            [['en' => 'Notification of Acceptance', 'id' => 'Pengumuman Penerimaan'], now()->addMonths(3), false],
            [['en' => 'Camera-Ready Deadline', 'id' => 'Batas Camera-Ready'], now()->addMonths(3)->addDays(15), false],
            [['en' => 'Conference Days', 'id' => 'Hari Konferensi'], now()->addMonths(4), true],
        ];
        foreach ($dates as $i => [$label, $date, $hl]) {
            ImportantDate::updateOrCreate(
                ['edition_id' => $eid, 'label->en' => $label['en']],
                ['label' => $label, 'date' => $date, 'is_highlighted' => $hl, 'order' => $i],
            );
        }

        foreach ([
            ['en' => 'Strategic & Sustainable Management', 'id' => 'Manajemen Strategis & Berkelanjutan'],
            ['en' => 'Digital Business & Innovation', 'id' => 'Bisnis Digital & Inovasi'],
            ['en' => 'Finance & Accounting', 'id' => 'Keuangan & Akuntansi'],
            ['en' => 'Marketing & Consumer Behavior', 'id' => 'Pemasaran & Perilaku Konsumen'],
        ] as $i => $title) {
            Topic::updateOrCreate(['edition_id' => $eid, 'title->en' => $title['en']], ['title' => $title, 'order' => $i]);
        }

        foreach ([
            [['en' => 'Presenter (Domestic)', 'id' => 'Pemakalah (Dalam Negeri)'], 1500000, 2000000],
            [['en' => 'Presenter (International)', 'id' => 'Pemakalah (Luar Negeri)'], 2500000, 3000000],
            [['en' => 'Participant', 'id' => 'Peserta'], 500000, 750000],
        ] as $i => [$cat, $early, $reg]) {
            RegistrationFee::updateOrCreate(
                ['edition_id' => $eid, 'category->en' => $cat['en']],
                ['category' => $cat, 'price_early_bird' => $early, 'price_regular' => $reg, 'currency' => 'IDR', 'order' => $i],
            );
        }

        foreach ([
            [['en' => 'Is online presentation allowed?', 'id' => 'Apakah presentasi daring diperbolehkan?'], ['en' => 'Yes, hybrid participation is supported.', 'id' => 'Ya, partisipasi hybrid didukung.']],
            [['en' => 'What is the paper format?', 'id' => 'Apa format paper-nya?'], ['en' => 'Use the official template on the Call for Papers page.', 'id' => 'Gunakan template resmi di halaman Call for Papers.']],
        ] as $i => [$q, $a]) {
            Faq::updateOrCreate(['edition_id' => $eid, 'question->en' => $q['en']], ['question' => $q, 'answer' => $a, 'order' => $i]);
        }

        Sponsor::updateOrCreate(['edition_id' => $eid, 'name' => 'Bank Contoh'], ['tier' => 'gold', 'order' => 0]);
        Sponsor::updateOrCreate(['edition_id' => $eid, 'name' => 'Media Partner X'], ['tier' => 'media_partner', 'order' => 1]);

        News::updateOrCreate(
            ['slug' => 'submission-deadline-extended'],
            [
                'edition_id' => $eid,
                'title' => ['en' => 'Submission Deadline Extended', 'id' => 'Batas Pengumpulan Diperpanjang'],
                'excerpt' => ['en' => 'The full paper submission deadline has been extended by two weeks.', 'id' => 'Batas pengumpulan full paper diperpanjang dua minggu.'],
                'content' => ['en' => '<p>Due to many requests, the deadline is extended.</p>', 'id' => '<p>Karena banyak permintaan, batas waktu diperpanjang.</p>'],
                'published_at' => now()->subDays(3),
                'is_published' => true,
            ],
        );

        Page::updateOrCreate(
            ['slug' => 'about'],
            [
                'edition_id' => $eid,
                'title' => ['en' => 'About the Conference', 'id' => 'Tentang Konferensi'],
                'content' => ['en' => '<p>ICOMAN 2026 brings together scholars and practitioners in management.</p>', 'id' => '<p>ICOMAN 2026 mempertemukan akademisi dan praktisi manajemen.</p>'],
                'is_published' => true,
            ],
        );
        Page::updateOrCreate(
            ['slug' => 'venue'],
            [
                'edition_id' => $eid,
                'title' => ['en' => 'Venue & Accommodation', 'id' => 'Lokasi & Akomodasi'],
                'content' => ['en' => '<p>The conference will be held in Makassar, Indonesia.</p>', 'id' => '<p>Konferensi diselenggarakan di Makassar, Indonesia.</p>'],
                'is_published' => true,
            ],
        );
        Page::updateOrCreate(
            ['slug' => 'publication'],
            [
                'edition_id' => $eid,
                'title' => ['en' => 'Publication & Indexing', 'id' => 'Publikasi & Indexing'],
                'content' => [
                    'en' => '<p>Selected papers will be published in Scopus-indexed proceedings and partner journals (SINTA 2). All papers receive an ISBN and DOI.</p>',
                    'id' => '<p>Paper terpilih akan diterbitkan pada prosiding terindeks Scopus dan jurnal mitra (SINTA 2). Setiap paper memperoleh ISBN dan DOI.</p>',
                ],
                'is_published' => true,
            ],
        );
    }
}
