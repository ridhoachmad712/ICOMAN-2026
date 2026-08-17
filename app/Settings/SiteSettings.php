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

    public static function group(): string
    {
        return 'site';
    }
}
