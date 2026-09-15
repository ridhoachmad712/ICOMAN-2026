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

    // Payment gateway Midtrans (dikelola dari admin; fallback ke .env bila dikosongkan).
    public ?string $midtrans_merchant_id;

    public ?string $midtrans_client_key;

    public ?string $midtrans_server_key;   // disimpan ter-enkripsi (lihat encrypted()).

    public bool $midtrans_is_production;

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

    public static function group(): string
    {
        return 'site';
    }

    /** Server key adalah rahasia: enkripsi saat disimpan di database. */
    public static function encrypted(): array
    {
        return ['midtrans_server_key'];
    }
}
