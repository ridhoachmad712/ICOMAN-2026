# ARCHITECTURE - ICOMAN 2026 CMS Website

## 1. Tech Stack

| Layer | Pilihan | Alasan |
|---|---|---|
| Backend Framework | Laravel 13 | Framework utama dengan dukungan keamanan aktif |
| Admin Panel | Filament v4 | CRUD dan panel operasional panitia |
| Frontend Publik | Blade + Tailwind CSS + Alpine.js | Ringan, tanpa build SPA berat, SEO-friendly (server-rendered) |
| Interaktivitas ringan | Livewire (opsional, hanya untuk komponen kecil: filter jadwal, form kontak) | Sudah include via Filament, tidak perlu dependency tambahan |
| Database | MySQL/MariaDB (sesuai hosting kampus) | Standar hosting Hostinger/kampus |
| Media Storage | Local disk + Spatie Media Library | Auto-resize, konversi WebP, cocok untuk foto speaker/sponsor |
| Site Settings | `spatie/laravel-settings` | Setting global tanpa migration ulang tiap tambah field |
| Slug otomatis | `spatie/laravel-sluggable` | Untuk URL halaman/news |
| Multi-bahasa | `spatie/laravel-translatable` | Field JSON translatable (EN/ID), tanpa tabel terpisah per bahasa |
| Role & Permission | `filament-shield` (bakumatoshi) atau Spatie Permission langsung | Role Superadmin, Admin Konten, Reviewer |
| SEO Meta | `artesaos/seotools` atau custom field per-page | Meta title/desc/OG per halaman |
| Autentikasi Publik | `laravel/fortify` atau Breeze (headless, tanpa scaffolding Blade default — dibuat custom sesuai desain) | Guard terpisah (`author`) dari guard admin Filament (`web`) |
| Payment Gateway | `midtrans/midtrans-php` (utama, umum dipakai instansi Indonesia) + fallback manual transfer | Dukung dua jalur pembayaran sesuai keputusan scope |
| Notifikasi Email | Laravel Notification + Mailable bawaan (queue via `database` atau `sync` untuk MVP) | Notifikasi status submission/registrasi |
| File Paper | Spatie Media Library (collection khusus `paper`, restrict mime docx/pdf) | Reuse infrastruktur media yang sama dengan foto |

> **Catatan penting**: Dengan masuknya submission-review dan registrasi-pembayaran, aplikasi ini sekarang punya **dua guard**: `web` (admin Filament — panitia/reviewer) dan `author` (portal publik — author/peserta). Jangan campur logic keduanya; reviewer TETAP diakses lewat guard `web`/Filament dengan role terbatas (bukan guard `author`), supaya tidak perlu bangun 2 sistem auth penuh.

## 2. Prinsip Desain

1. **Content-driven, bukan hardcoded**: Semua teks yang mungkin berubah (nama conference, tanggal, kontak) disimpan di DB / settings, bukan di Blade.
2. **Satu source of truth untuk tema**: Warna, logo di `SiteSettings` — dipakai di seluruh layout via CSS variables.
3. **Public routes read-only & cache-friendly**: Halaman publik cache per-route (Laravel response cache / full page cache sederhana) karena konten jarang berubah dalam hitungan menit.
4. **Admin panel terisolasi**: `/admin` prefix, guard terpisah dari (jika nanti ada) user publik.
5. **Siap multi-tahun**: Tabel utama (speakers, committees, schedules, dst.) punya kolom `edition_id` yang menunjuk ke tabel `editions` (mis. "ICOMAN 2026"). Untuk MVP hanya 1 edition aktif, tapi struktur sudah antisipasi ICOMAN 2027 tanpa rebuild.

## 3. Struktur Direktori (ringkas)

```
app/
  Filament/
    Resources/
      SpeakerResource.php
      CommitteeResource.php
      NewsResource.php
      SponsorResource.php
      ScheduleResource.php
      ImportantDateResource.php
      RegistrationFeeResource.php
      FaqResource.php
      DownloadResource.php
      PageResource.php
      GalleryResource.php
    Pages/
      Settings/SiteSettingsPage.php   (form untuk spatie/laravel-settings)
  Models/
    Edition.php
    Speaker.php
    Committee.php
    Topic.php
    ImportantDate.php
    RegistrationFee.php
    News.php
    Sponsor.php
    Faq.php
    Schedule.php
    Download.php
    Page.php
    Gallery.php
    ContactMessage.php
  Settings/
    SiteSettings.php
  Http/Controllers/
    HomeController.php
    PageController.php   (about, venue, dsb — dynamic pages)
    NewsController.php
    ContactController.php
resources/
  views/
    layouts/app.blade.php
    components/ (navbar, footer, hero, card-speaker, dsb.)
    home.blade.php
    about.blade.php
    committee.blade.php
    speakers.blade.php
    call-for-papers.blade.php
    registration.blade.php
    schedule.blade.php
    news/index.blade.php + show.blade.php
    contact.blade.php
```

## 4. Alur Data Contoh (Speaker)

1. Admin login `/admin` → buka **Speakers** resource → isi nama, foto, afiliasi, topik, pilih tipe (Keynote/Invited) → simpan.
2. `SpeakerResource` (Filament) menyimpan ke tabel `speakers`, foto lewat Media Library collection `photo`.
3. Halaman publik `/speakers` (via `HomeController@speakers` atau route langsung) query `Speaker::where('edition_id', currentEdition())->orderBy('order')->get()`.
4. Blade render pakai component `<x-card-speaker :speaker="$speaker" />`.

## 5. Performa & "Ringan"

- Gunakan Tailwind via CDN-free build (Vite) dengan purge otomatis — hasil CSS kecil.
- Alpine.js hanya untuk: mobile menu toggle, countdown timer, FAQ accordion, lightbox gallery. Tidak perlu React/Vue.
- Gambar upload otomatis dikonversi ke ukuran & format WebP via Media Library conversions (`thumb`, `card`, `full`).
- Cache halaman publik dengan `Cache::remember()` per query berat (misal daftar speaker, schedule) — invalidasi otomatis saat admin update (model event `saved`/`deleted`).
- Lazy-load image (`loading="lazy"`) di semua `<img>` publik.

## 5b. Modul Submission, Review, Registrasi — Struktur Tambahan

```
app/
  Models/
    User.php                (guard: author — public)
    Submission.php
    SubmissionAuthor.php
    Reviewer.php            (bisa berupa role pada User admin/Filament, bukan guard baru)
    ReviewAssignment.php
    Review.php
    Registration.php
    Payment.php
  Filament/Resources/
    SubmissionResource.php
    ReviewAssignmentResource.php
    RegistrationResource.php
    ReviewerResource.php (jika reviewer dikelola sebagai user Filament terbatas)
  Http/Controllers/Author/
    AuthController.php      (register/login khusus guard author)
    SubmissionController.php
    RegistrationController.php  (form registrasi + pilih metode bayar)
  Http/Controllers/Payment/
    MidtransController.php  (create transaction + webhook/notification handler)
  Notifications/
    SubmissionStatusChanged.php
    RegistrationPaymentReceived.php
resources/views/author/
  dashboard.blade.php
  submissions/create.blade.php + index.blade.php + show.blade.php
  registration/create.blade.php + show.blade.php
```

Webhook Midtrans harus punya route publik tanpa CSRF (`withoutMiddleware(VerifyCsrfToken::class)` atau exclude di `bootstrap/app.php`), dan **wajib** verifikasi signature key sebelum update status pembayaran — jangan percaya payload begitu saja.

## 6. Keamanan

- `/admin` pakai Filament default auth + rate limiter login.
- Role: `superadmin` (full access + user management + site settings), `content_admin` (CRUD konten, tanpa akses user management).
- Form kontak publik pakai honeypot + rate limit untuk cegah spam bot (tanpa perlu Google reCAPTCHA dulu, bisa ditambah belakangan).
