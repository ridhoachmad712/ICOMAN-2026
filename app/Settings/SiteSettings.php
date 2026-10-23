<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SiteSettings extends Settings
{
    public string $conference_name;

    public ?string $logo;      // path di storage (diisi via Filament FileUpload)

    public ?string $favicon;   // path di storage

    public ?string $primary_color;

    public ?string $secondary_color;

    public ?string $contact_email;

    public ?string $contact_whatsapp;

    public ?string $contact_address;

    /** Undangan grup WhatsApp peserta; hanya ditampilkan kepada yang sudah lunas. */
    public ?string $whatsapp_group_url;

    /**
     * Akses Zoom konferensi. Hanya tampil di dashboard peserta yang berhak:
     * peserta seminar yang lunas, dan presenter yang lunas serta sudah
     * mengumpulkan full paper (lihat Author::canJoinConference()).
     */
    public ?string $zoom_url;

    public ?string $zoom_meeting_id;

    public ?string $zoom_passcode;

    public ?string $zoom_note;

    /**
     * Template sertifikat. Latar dan tanda tangan berupa path gambar di disk
     * publik; tanpa latar, sertifikat memakai desain bawaan berwarna merek.
     */
    public ?string $certificate_background;

    public ?string $certificate_signatory_name;

    public ?string $certificate_signatory_title;

    public ?string $certificate_signature;

    public ?string $social_instagram;

    public ?string $social_twitter;

    public ?string $social_youtube;

    public ?string $google_maps_embed_url;

    public string $default_locale; // 'en' | 'id'

    // Info rekening untuk pembayaran manual (transfer + upload bukti).
    public ?string $bank_name;

    public ?string $bank_account_number;

    public ?string $bank_account_holder;

    // Hero homepage.
    public ?string $event_location;   // mis. "Makassar, Indonesia"

    public ?string $event_mode;       // mis. "Hybrid (Onsite & Online)"

    public ?string $hero_image;       // path gambar hero

    // Penyelenggara (host).
    public ?string $organizer_name;

    public ?string $organizer_logo;   // path logo host

    // Biaya tambahan penerbitan jurnal SINTA 3 (ditambahkan ke registrasi presenter).
    public int $sinta3_fee;

    /** Tautan template naskah final, dipilih menurut target jurnal paper. */
    public ?string $paper_template_regular_url;

    public ?string $paper_template_sinta3_url;

    // Payment gateway BorderPay (dikelola dari admin; fallback ke .env bila dikosongkan).
    // Keduanya rahasia dan disimpan ter-enkripsi (lihat encrypted()).
    public ?string $borderpay_api_key;

    // Token statis, BUKAN signing secret: webhook BorderPay tidak
    // ditandatangani, jadi token ini hanya menjaga pintu.
    public ?string $borderpay_webhook_token;

    /** Tipografi menyeluruh (lihat FONTS untuk pilihan yang tersedia). */
    public ?string $font_heading;

    public ?string $font_body;

    public ?int $base_font_size;

    /**
     * Huruf yang boleh dipilih. Semuanya diambil dari Google Fonts, sumber yang
     * memang sudah dipakai halaman publik — jadi memilih di sini tidak
     * menambah ketergantungan baru.
     */
    public const FONTS = [
        'Instrument Sans' => 'Instrument Sans',
        'Space Grotesk' => 'Space Grotesk',
        'Inter' => 'Inter',
        'Plus Jakarta Sans' => 'Plus Jakarta Sans',
        'Poppins' => 'Poppins',
        'Montserrat' => 'Montserrat',
        'Lora' => 'Lora (serif)',
        'Merriweather' => 'Merriweather (serif)',
        'Playfair Display' => 'Playfair Display (serif)',
        'Source Serif 4' => 'Source Serif (serif)',
    ];

    /**
     * Warna yang dipakai bila Pengaturan dikosongkan.
     *
     * Sebelumnya tiap tempat memilih cadangannya sendiri: situs publik jatuh ke
     * biru, portal author ke jingga. Selama Pengaturan terisi keduanya tampak
     * sama dan selisih itu tersembunyi, tapi begitu dikosongkan satu produk
     * berubah menjadi dua identitas. Nilainya diambil dari logo konferensi.
     */
    public const DEFAULT_BRAND = '#d9621c';

    public const DEFAULT_BRAND_2 = '#13355c';

    /** Warna merek yang berlaku, dari Pengaturan bila diisi. */
    public function brandColor(): string
    {
        return $this->primary_color ?: self::DEFAULT_BRAND;
    }

    public function brandColor2(): string
    {
        return $this->secondary_color ?: self::DEFAULT_BRAND_2;
    }

    public function paperTemplateUrlFor(?string $journalTarget): ?string
    {
        $url = $journalTarget === 'sinta3'
            ? $this->paper_template_sinta3_url
            : $this->paper_template_regular_url;

        return filled($url) ? trim($url) : null;
    }

    public static function group(): string
    {
        return 'site';
    }

    /** Kredensial gateway adalah rahasia: enkripsi saat disimpan di database. */
    public static function encrypted(): array
    {
        return ['borderpay_api_key', 'borderpay_webhook_token'];
    }
}
