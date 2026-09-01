# PROGRESS & HANDOFF — ICOMAN 2026 CMS

> Dokumen kontinuitas. Baca ini **pertama** saat melanjutkan proyek di platform/agent/akun lain.
> Terakhir diperbarui: **2026-08 (setelah Fase 0–5 MVP + polish frontend/backend)**.
> Referensi lain di root: `CLAUDE.md`, `PRD.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `ROADMAP.md`, `CONTENT_CHECKLIST.md`.

---

## 1. TL;DR Status

**MVP (Fase 0–5) SELESAI & teruji end-to-end.** Website conference lengkap: CMS publik bilingual + portal author (submission & review) + registrasi & pembayaran ganda (manual + Midtrans). Sudah ditambah banyak polish UI/UX (design system, SEO dasar, panel admin di-branding + 2FA + user management).

**Belum:** Fase 6 sisa (SEO per-halaman, uji Lighthouse) & Fase 7 (Deploy). Kredensial Midtrans belum diisi. Konten masih dummy (DevSeeder).

---

## 2. Stack & DEVIASI PENTING dari dokumen awal

Dokumen awal (`CLAUDE.md`/`ARCHITECTURE.md`) menyebut **Laravel 11 + Filament v3**. Itu **SUDAH DIGANTI** (disetujui user) — jangan ikuti versi lama:

| Item | Dipakai | Alasan deviasi |
|---|---|---|
| **Laravel** | **13.25** (`^13.17`) | Laravel 11 sudah EOL keamanan per 2026-08 (advisory HIGH CRLF injection tak tertambal, diblokir Composer) |
| **Filament** | **v4.12** (`^4.0`) | Konsekuensi Laravel 13 |
| PHP | 8.3.28 | |
| DB | MySQL (`icoman_2026`) | |
| Node | 25 · Vite 8 · **Tailwind v4** | |

**Paket Composer terpasang:** filament/filament ^4, filament/spatie-laravel-media-library-plugin ^4, filament/spatie-laravel-settings-plugin ^4, spatie/laravel-medialibrary ^11, spatie/laravel-permission ^8, spatie/laravel-settings ^3, spatie/laravel-sluggable ^4, spatie/laravel-translatable ^6, midtrans/midtrans-php ^2.6.

**Paket npm:** tailwindcss ^4, @tailwindcss/vite, @tailwindcss/typography, flag-icons ^7 (bendera self-hosted), laravel-vite-plugin ^3, vite ^8. (`alpinejs` ada di package.json tapi TIDAK diimport — Alpine disediakan Livewire; lihat §5.)

**GAGAL/TIDAK dipakai:**
- `filament/spatie-laravel-translatable-plugin` → **tak kompatibel Filament v4** (max v3). Bilingual di admin pakai **field per-locale manual** (lihat §5).
- Query/model caching → **sengaja tak dipakai** (caching model Eloquent via DB driver menyebabkan error serialisasi "incomplete object"; full-page cache bentrok CSRF/Livewire). Perf via WebP + eager-load.
- `filament-shield` → tidak dipakai; role pakai Spatie Permission manual.

---

## 3. Keputusan scope yang SUDAH dikonfirmasi user (JANGAN tanya ulang)

1. **Hosting**: Hostinger + Cloudflare, mendukung queue worker/cron → `QUEUE_CONNECTION=database`.
2. **Payment gateway**: **Midtrans** (`midtrans/midtrans-php`, Snap + webhook signature).
3. **Reviewer**: **dosen internal**, akun dibuat admin → guard `web` + role `reviewer` (bukan self-register).
4. **Skor review**: skala **1–100** skor tunggal → `reviews.score` integer.
5. Bilingual EN/ID aktif; submission-review dibangun sendiri; pembayaran dua jalur (manual + gateway).

---

## 4. Status per Fase

| Fase | Status | Catatan |
|---|---|---|
| 0 Setup | ✅ | L13 + Filament v4 + Spatie + Tailwind/Vite |
| 1 Migration & Model | ✅ | 21 tabel bisnis, HasTranslations/HasMedia, seeder Edition aktif |
| 2 Admin Panel | ✅ | 13 resource CRUD + reorderable + Settings page + role + widget |
| 3 Frontend publik | ✅ | Bilingual, countdown, ~13 halaman, form kontak Livewire, language switcher |
| 4 Portal author & submission | ✅ | Guard author, register/login/reset, submit + co-author, assign reviewer, form review, `changeStatus()` + email, camera-ready |
| 5 Registrasi & pembayaran | ✅ | Form registrasi, manual (upload bukti + verifikasi admin), Midtrans + webhook signature, payments audit, rekap |
| **Polish frontend** | ✅ | Design system (.btn/.card, Space Grotesk), hero upgrade, statistik/teaser/CTA, bendera negara, konsistensi semua halaman, portal author bilingual, scroll-reveal |
| **Polish backend** | ✅ | Panel branding, Profile (ganti password), 2FA opt-in, UserResource, dashboard widgets (tabel+chart), global search, CSV export, relation manager co-author, auto-read pesan |
| 6 SEO & Performa | ⚠️ sebagian | SUDAH: JSON-LD Event, meta/OG/canonical, sitemap.xml, konversi WebP, lazy. BELUM: meta per-halaman/breadcrumb, uji Lighthouse |
| 7 Deploy | ❌ | Belum mulai |

---

## 5. Cheat-sheet Arsitektur (konvensi yang WAJIB diikuti)

- **Dua guard TERPISAH**: `web` (admin/Filament, tabel `users` + Spatie roles) & `author` (portal publik, tabel `authors`). Config di `config/auth.php`. Guest ber-guard author redirect ke `author.login` via `redirectGuestsTo` di `bootstrap/app.php`.
- **Helper** di `app/Support/helpers.php` (autoload `files` di composer.json):
  - `currentEdition()` — edition aktif (di-scope semua query publik).
  - `siteSettings()` — instance `App\Settings\SiteSettings`.
  - `countryCode()/countryName()/countries()` — negara (ISO2) untuk speaker + bendera.
- **Bilingual di admin (MANUAL, bukan plugin)**: field per-locale `title.en` + `title.id`; Edit page pakai trait `App\Filament\Concerns\ExpandsTranslationsOnFill` (expand JSON → array saat load; Spatie simpan array otomatis saat save).
- **Status submission**: WAJIB via `Submission::changeStatus()` (memicu notifikasi email `SubmissionStatusChanged`). Jangan `update(['status'=>...])` langsung.
- **Webhook Midtrans**: `POST /payment/midtrans/notification` (CSRF-exempt di bootstrap/app.php). WAJIB `MidtransService::verifySignature()` sebelum percaya payload. Logika di `app/Services/MidtransService.php`.
- **Design system (frontend)**: `resources/css/app.css` `@layer components` → `.btn/.btn-primary/.btn-ghost/.btn-outline`, `.card/.card-hover`, `.section-tint`, `.avatar-fallback`. Font display **Space Grotesk** (heading), body Instrument Sans. Warna brand runtime dari SiteSettings via CSS var `--brand`/`--brand-2` (di-inject di `layouts/app.blade.php`).
- **Alpine.js**: disediakan **Livewire (bundled)** — layout publik & author muat `@livewireStyles`/`@livewireScripts`. **JANGAN import Alpine di app.js** (error "multiple instances").
- **Scroll-reveal**: `[data-reveal]` + IntersectionObserver di `app.js`, dengan **fail-safe timeout 1200ms** (konten tak pernah stuck hidden) + gated `.js` class.
- **Media**: semua gambar via Spatie Media Library (JANGAN kolom path manual). Konversi WebP `thumb`/`card` (nonQueued) di Speaker/Committee/Sponsor/News/Gallery.
- **Role**: `superadmin` (penuh + Users + Settings), `content_admin` (konten), `reviewer` (hanya "My Reviews"). Resource konten & Submission/Registration di-`canAccess()` gate; ReviewAssignmentResource scoped ke reviewer.

---

## 6. Kredensial & konfigurasi LOKAL (dev)

> Ini kredensial **development**. Untuk production semua harus diganti.

| Guna | Nilai |
|---|---|
| DB | MySQL host 127.0.0.1, db `icoman_2026`, user `root`, password **kosong** |
| Superadmin (`/admin`) | Buat melalui seeder lokal; jangan simpan password di repository |
| Reviewer uji (`/admin`) | Buat melalui `DevSeeder` dan gunakan kredensial lokal sementara |
| Author uji (`/author/login`) | Buat melalui `DevSeeder` dan gunakan kredensial lokal sementara |
| Mail (dev) | `MAIL_MAILER=log` → email masuk `storage/logs/laravel.log` |
| Midtrans | `.env` `MIDTRANS_SERVER_KEY`/`MIDTRANS_CLIENT_KEY` **KOSONG** (isi sebelum uji gateway); `MIDTRANS_IS_PRODUCTION=false` |

Warna brand saat ini: primary `#d9621c` (oranye) + secondary `#18315e` (navy) — di Site Settings, bisa diubah.

---

## 7. Cara menjalankan / rebuild

```bash
composer install
npm install
npm run build
php artisan migrate --seed          # Role + Edition seeder (BUKAN data dummy)
php artisan serve                    # http://127.0.0.1:8000
```

Untuk **data preview** (dummy, dev-only, opt-in):
```bash
php artisan db:seed --class=DevSeeder
```

Untuk **notifikasi email** jalan, hidupkan worker:
```bash
php artisan queue:work
```

Reset DB ke bersih (hapus dummy):
```bash
php artisan migrate:fresh --seed
```

⚠️ **GOTCHA UTAMA — tampilan berantakan?** Cek file `public/hot`. Kalau ada (sisa `npm run dev`), `@vite` menunjuk ke Vite dev server (port 5173) yang mati → CSS tak termuat. **Hapus `public/hot`** lalu refresh. Mode dev: jalankan `php artisan serve` + `npm run dev` bersamaan, hentikan `npm run dev` dengan Ctrl+C (agar `hot` terhapus).

---

## 8. Yang TERSISA (prioritas untuk dilanjut)

**Sebelum go-live (wajib):**
1. **Isi konten asli** via `/admin` (lihat `CONTENT_CHECKLIST.md`) — sekarang semua dummy.
2. **Buat akun reviewer asli** (dosen internal) via `/admin` → Users & Roles.
3. **Kredensial Midtrans** di `.env` (sandbox → production) + set URL notifikasi webhook di dashboard Midtrans ke `https://<domain>/payment/midtrans/notification`.
4. **Ganti password superadmin** sementara.

**Fase 6 sisa (SEO/perf):** meta description per-halaman (kini hanya homepage punya JSON-LD), breadcrumb JSON-LD, uji **Lighthouse** (target PRD ≥85) di Chrome DevTools.

**Fase 7 Deploy:** subdomain (pola `manajemen-feb.unm.ac.id` di Hostinger — antisipasi isu Cloudflare proxy/SSL), `.env` production, `php artisan optimize`, **queue worker aktif** (email+webhook), backup DB+storage, uji E2E kedua jalur pembayaran (manual & Midtrans sandbox).

**Opsional/pasca-MVP:** sertifikat PDF otomatis, arsip multi-edition di UI publik, Xendit sebagai gateway kedua, export ke Excel (kini CSV), reviewAssignments relation manager, filament-shield.

---

## 9. Verifikasi yang SUDAH dilakukan (biar tak uji ulang)

Terverifikasi di browser/tinker: register+login author (guard terpisah), submit paper (nomor auto `ICOMAN2026-0001`, co-author, media, notifikasi ter-queue), assign reviewer → status auto, isi review, `changeStatus` → email ter-render ke log, camera-ready, registrasi manual (bank info + bukti + verifikasi admin), **webhook Midtrans: signature valid→paid / invalid→403**, akses role (reviewer 403 dari resource konten), bilingual EN/ID (konten model + UI), homepage semua section, panel: Profile+2FA+Users&Roles+global search+export CSV+widget.

**Batasan lingkungan uji:** pane preview browser di sesi pengembangan **tidak meng-compositing** → screenshot & animasi/transisi CSS & widget lazy (IntersectionObserver) tak bisa diverifikasi visual; diverifikasi via computed-style/DOM/tinker. **Perlu cek mata di Chrome asli** untuk finalisasi visual.
