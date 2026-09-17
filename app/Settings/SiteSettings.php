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
