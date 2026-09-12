<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Kebijakan privasi sebelumnya tertulis tetap di Blade — dokumen legal yang
 * tidak bisa disentuh admin sama sekali. Isinya dipindahkan apa adanya menjadi
 * halaman CMS supaya bisa disunting seperti halaman About dan Venue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Page::where('slug', 'privacy')->exists()) {
            return;
        }

        Page::create([
            'slug' => 'privacy',
            'is_published' => true,
            'title' => [
                'id' => 'Privasi dan penggunaan data',
                'en' => 'Privacy and data use',
            ],
            'meta_title' => [
                'id' => 'Privasi dan penggunaan data',
                'en' => 'Privacy and data use',
            ],
            'content' => [
                'id' => $this->body(
                    'Halaman ini menjelaskan penggunaan data dalam portal konferensi.',
                    'Data yang diproses',
                    'Nama, email, afiliasi, negara, nomor kontak, kategori peserta, data penulis, abstrak, naskah, hasil review, persetujuan syarat, serta catatan registrasi dan pembayaran digunakan untuk mengelola akun dan penyelenggaraan konferensi.',
                    'Akses dan pembayaran',
                    'Naskah dapat diakses pemilik, reviewer yang ditugaskan, dan panitia yang berwenang. Pembayaran diproses melalui Midtrans; nama, email, nomor kontak, dan rincian transaksi diteruskan saat checkout. Informasi kartu dimasukkan pada halaman penyedia pembayaran.',
                    'Pertanyaan atau koreksi data',
                    'Gunakan halaman kontak untuk meminta koreksi data, menanyakan penyimpanan data, atau menyampaikan permintaan penghapusan. Panitia menangani permintaan sesuai kebutuhan administrasi dan pencatatan konferensi.',
                ),
                'en' => $this->body(
                    'This page describes how data is used in the conference portal.',
                    'Data processed',
                    'Names, email addresses, affiliations, countries, contact numbers, participant categories, author details, abstracts, manuscripts, reviews, terms acceptance, and registration and payment records are used to operate accounts and organize the conference.',
                    'Access and payment',
                    'Manuscripts are accessible to their owner, assigned reviewers, and authorized committee members. Payments are processed through Midtrans; the name, email, contact number, and transaction details are sent at checkout. Card details are entered on the payment provider’s page.',
                    'Questions or data corrections',
                    'Use the contact page to request a correction, ask about data retention, or submit a deletion request. The committee handles requests in accordance with conference administration and recordkeeping needs.',
                ),
            ],
        ]);
    }

    public function down(): void
    {
        Page::where('slug', 'privacy')->delete();
    }

    private function body(string $intro, string ...$sections): string
    {
        $html = '<p>'.e($intro).'</p>';

        foreach (array_chunk($sections, 2) as [$heading, $text]) {
            $html .= '<h2>'.e($heading).'</h2><p>'.e($text).'</p>';
        }

        return $html;
    }
};
