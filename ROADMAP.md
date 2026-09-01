# ROADMAP - ICOMAN 2026 CMS Website

> ℹ️ **Status aktual ada di `PROGRESS.md`**, bukan di checkbox `[ ]` di bawah (itu rencana awal). Ringkas: **Fase 0–5 SELESAI**; sisa Fase 6 (SEO per-halaman, Lighthouse) & Fase 7 (Deploy).

## Fase 0 — Setup Environment (0.5 hari)
- [ ] `laravel new icoman-2026-cms`
- [x] Install Filament v4 (`filament:install --panels`)
- [ ] Install Tailwind + Vite config untuk frontend publik (terpisah dari asset Filament)
- [ ] Install package: `spatie/laravel-medialibrary`, `spatie/laravel-translatable`, `spatie/laravel-sluggable`, `spatie/laravel-settings`, `spatie/laravel-permission`
- [ ] Setup `.env`, database lokal, `php artisan migrate`
- [ ] Setup akun superadmin awal (`php artisan make:filament-user`)

## Fase 1 — Database & Model (1 hari)
- [ ] Migration semua tabel di `DATABASE_SCHEMA.md`
- [ ] Model + relasi + trait `HasTranslations` untuk field (T)
- [ ] Seeder minimal: 1 `Edition` ("ICOMAN 2026", is_active=true) agar sistem langsung bisa dipakai
- [ ] Factory dummy data (opsional, untuk testing tampilan sebelum data asli masuk)

## Fase 2 — Admin Panel Filament (1.5–2 hari)
- [ ] Resource CRUD untuk setiap modul: Speakers, Committees, Topics, ImportantDates, RegistrationFees, Schedules, Downloads, Sponsors, News, Galleries, Faqs, Pages
- [ ] Reorderable (drag-sort) untuk resource yang punya kolom `order`
- [ ] Halaman Settings custom (`SiteSettingsPage`) untuk site_settings global
- [ ] Setup role & permission: Superadmin vs Content Admin (`filament-shield` atau manual policy)
- [ ] Widget dashboard sederhana: jumlah speaker, hari tersisa ke conference, pesan kontak belum dibaca

## Fase 3 — Frontend Publik (2–3 hari)
- [ ] Layout dasar (navbar, footer, mobile menu via Alpine)
- [ ] Homepage: hero + countdown, ringkasan about, highlight speaker, important dates preview, sponsor logos
- [ ] Halaman: About, Committee, Speakers, Call for Papers, Important Dates, Registration, Author Guidelines/Downloads, Program/Schedule, Venue, News (index + detail), FAQ, Contact
- [ ] Form kontak (Livewire component sederhana + honeypot anti-spam)
- [ ] Language switcher EN/ID (session-based locale)

## Fase 4 — Portal Author & Sistem Submission (2–3 hari)
- [ ] Setup guard `author` terpisah di `config/auth.php` (tabel `authors`, bukan `users`)
- [ ] Halaman register/login/forgot-password untuk author (custom view, bukan scaffolding default Breeze)
- [ ] Dashboard author: daftar submission miliknya + status
- [ ] Form submit paper: judul, abstrak, topik, upload file (validasi mime docx/pdf + ukuran max), tambah co-author dinamis (Livewire/Alpine repeater)
- [ ] Generate `submission_number` otomatis saat create
- [ ] Filament: `SubmissionResource` (list, filter by status/edition), aksi assign reviewer
- [ ] Filament: role `reviewer` (Spatie Permission) + `ReviewAssignmentResource` khusus reviewer (hanya lihat paper yang ditugaskan ke dirinya)
- [ ] Form review (di Filament, scoped ke assignment miliknya) → simpan ke `reviews`
- [ ] Method terpusat `Submission::changeStatus()` + notifikasi email di setiap perubahan status
- [ ] Upload camera-ready setelah status `accepted`

## Fase 5 — Registrasi & Pembayaran Ganda (2 hari)
- [ ] Form registrasi publik: pilih `registration_fee`, isi data, pilih metode bayar
- [ ] Jalur **manual**: tampilkan info rekening dari `site_settings` → upload bukti transfer → status `pending_verification` → Filament `RegistrationResource` punya aksi "Verifikasi Pembayaran"
- [ ] Jalur **gateway**: integrasi `midtrans/midtrans-php` (Snap/redirect), route callback/webhook + **verifikasi signature key** sebelum update status
- [ ] Catat setiap transaksi ke tabel `payments` (audit trail, termasuk yang gagal)
- [ ] Dashboard author: status registrasi & riwayat pembayaran
- [ ] Dashboard admin: rekap peserta per kategori & status bayar

## Fase 6 — SEO, Performa, Polish (1 hari)
- [ ] Meta title/description dinamis per halaman
- [ ] Sitemap.xml otomatis
- [ ] OG image default + per news/page
- [ ] Cache query berat (speakers, schedule, sponsors) dengan invalidasi via model event
- [ ] Optimasi gambar (Media Library conversions: thumb/card/full, format WebP)
- [ ] Uji Lighthouse mobile, perbaiki bottleneck

## Fase 7 — Deploy (1 hari)
- [ ] Setup subdomain (pola serupa `manajemen-feb.unm.ac.id` di Hostinger — antisipasi isu Cloudflare proxy/SSL yang sebelumnya ditemui)
- [ ] `.env` production, kredensial Midtrans mode **production** (bukan sandbox), `php artisan optimize`, queue/cache driver sesuai hosting
- [ ] Queue worker aktif (untuk email notifikasi & webhook processing) — pastikan hosting mendukung queue worker/cron, bukan cuma shared hosting statis
- [ ] Backup strategy (database + storage/media, termasuk file paper & bukti bayar)
- [ ] Testing end-to-end: registrasi author dummy → submit paper dummy → assign reviewer dummy → review → status accepted → registrasi bayar (uji KEDUA jalur: manual & gateway sandbox)

## Fase 8 (Pasca-MVP, opsional)
- [ ] Sertifikat otomatis (generate PDF nama peserta)
- [ ] Arsip multi-edition (ICOMAN 2025 sebagai referensi histori, tampil publik)
- [ ] Integrasi Xendit sebagai gateway kedua (jika Midtrans dirasa kurang cocok)
- [ ] Export data submission/registrasi ke Excel untuk laporan panitia

---

**Estimasi total MVP: ± 13–16 hari kerja efektif** (naik dari estimasi awal karena submission-review dan payment gateway dibangun sendiri, bukan pakai layanan eksternal). Asumsi 1 developer, dengan bantuan Claude Code untuk generate boilerplate resource & migration.
