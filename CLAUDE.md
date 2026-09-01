# CLAUDE.md — ICOMAN 2026 CMS Website

> ⚠️ **BACA `PROGRESS.md` DULU.** Proyek ini SUDAH DIBANGUN (MVP Fase 0–5 selesai + polish). Dokumen ini berisi rencana AWAL; beberapa sudah usang. Khususnya:
> - **Bukan Laravel 11 / Filament v3** lagi → sekarang **Laravel 13 + Filament v4** (Laravel 11 EOL keamanan; disetujui user).
> - Bilingual di admin pakai **field per-locale manual** (plugin translatable tak kompatibel Filament v4).
> - Status, kredensial, cara run, gotcha (`public/hot`), dan sisa pekerjaan → semua di **`PROGRESS.md`**.

## Ringkasan Project
Sistem manajemen International Conference on Management (ICOMAN) 2026: website CMS publik + portal author (submission & review paper) + registrasi & pembayaran ganda (manual & gateway). Laravel 13 + Filament v4 sebagai admin panel, frontend publik Blade + Tailwind + Alpine.js (ringan, tanpa SPA), autentikasi publik terpisah (guard `author`). Referensi lengkap ada di file sebelah: `PRD.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `ROADMAP.md`. Baca keempatnya sebelum mulai coding.

## Keputusan Scope (sudah dikonfirmasi user — jangan tanya ulang)
1. Bilingual EN/ID — **aktif** untuk seluruh konten CMS.
2. Sistem submission & review paper — **dibangun sendiri** (bukan link ke OJS/EasyChair eksternal).
3. Pembayaran registrasi — **mendukung dua jalur sekaligus**: manual transfer + upload bukti, DAN payment gateway otomatis (Midtrans).

## Status Saat Ini
- **MVP (Fase 0–5) SELESAI & teruji** + polish frontend/backend. Detail lengkap di `PROGRESS.md`. Sisa: Fase 6 (SEO per-halaman, Lighthouse) & Fase 7 (Deploy).
- Belum ada materi konten asli (nama komite, speaker, dsb.) — semua resource generik, diisi via admin (lihat `CONTENT_CHECKLIST.md`). Data preview dummy ada di `DevSeeder` (opt-in, dev-only). Jangan hardcode dummy sebagai default permanen.

## Konvensi Kode
- Laravel 13 dengan struktur default (tanpa Kernel.php terpisah, pakai `bootstrap/app.php` untuk middleware/route registration).
- Semua model dengan field bilingual pakai trait `Spatie\Translatable\HasTranslations` — daftar field translatable ada di `DATABASE_SCHEMA.md` (ditandai `(T)`).
- Semua model dengan gambar pakai `Spatie\MediaLibrary\HasMedia` — jangan simpan path gambar manual di kolom string.
- Filament Resource: satu resource per model, gunakan `Forms\Components` bawaan Filament dulu sebelum bikin custom field.
- Tabel dengan kolom `order` HARUS reorderable di Filament table (`->reorderable('order')`).
- Query publik ke model yang punya `edition_id` WAJIB di-scope ke edition aktif — buat helper `currentEdition()` di `app/Support/` dan gunakan konsisten, jangan query manual berulang di setiap controller.
- Frontend: gunakan Blade components (`resources/views/components/`) untuk elemen berulang (card speaker, card news, section heading) — hindari duplikasi markup antar halaman.
- JS minim: hanya Alpine.js untuk interaksi kecil (menu mobile, countdown, accordion FAQ, lightbox, repeater co-author di form submission). Jangan tambahkan framework JS lain.
- Guard `author` (portal publik) dan guard `web` (admin Filament/reviewer) HARUS terpisah — jangan campur tabel `users` (admin) dengan `authors` (peserta/pemakalah).
- Setiap perubahan `submissions.status` WAJIB lewat method terpusat (`Submission::changeStatus()`), bukan `update(['status' => ...])` langsung di controller/resource manapun — supaya notifikasi email konsisten terkirim.
- Webhook payment gateway WAJIB verifikasi signature/checksum sebelum mempercayai payload — jangan update status `registrations` hanya karena request masuk ke endpoint webhook.

## Yang HARUS Ditanyakan ke User Sebelum Lanjut (jangan asumsikan sendiri)
1. Nama domain/subdomain final dan target hosting (pola serupa Hostinger + Cloudflare seperti project sebelumnya?) — pastikan hosting mendukung **queue worker/cron**, karena notifikasi email & webhook payment butuh ini.
2. Payment gateway mana yang akun bisnisnya sudah/akan disiapkan: Midtrans atau Xendit (ARCHITECTURE.md default ke Midtrans, tapi konfirmasi dulu sebelum integrasi ditulis).
3. Skema role reviewer: apakah reviewer adalah dosen internal (akun dibuatkan admin) atau reviewer eksternal (perlu self-register)? Ini menentukan apakah reviewer pakai guard `web` (dibuatkan manual) atau perlu flow undangan/registrasi sendiri.
4. Format skor review: skala apa yang dipakai panitia (1–100, 1–5, atau rubrik multi-kriteria)? Ini menentukan struktur kolom `reviews.score`.

## Urutan Eksekusi
Ikuti fase di `ROADMAP.md` secara berurutan (Fase 0 → 5). Jangan lompat ke frontend (Fase 3) sebelum migration & model (Fase 1) selesai dan admin resource (Fase 2) minimal bisa create-read-update-delete dengan benar — supaya saat frontend dibangun, ada data nyata (walau dummy) untuk dites tampilannya.

## Testing Manual per Fase
Setiap kali satu modul (misal Speakers) selesai di Fase 2, langsung isi 1 data dummy via admin panel dan screenshot/cek hasilnya sebelum lanjut ke modul berikutnya — jangan bangun semua resource dulu baru dites di akhir.

## Larangan
- Jangan install package tambahan di luar yang disebut di `ARCHITECTURE.md` tanpa konfirmasi ke user.
- Jangan ubah struktur tabel di `DATABASE_SCHEMA.md` secara sepihak; jika perlu perubahan, sampaikan alasannya ke user dulu.
- Jangan hardcode teks yang seharusnya dikelola admin (nama conference, tanggal, kontak) langsung di Blade — semua harus dari DB/Settings.
