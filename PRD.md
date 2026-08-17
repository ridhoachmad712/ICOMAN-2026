# PRD - ICOMAN 2026 CMS Website

## 1. Latar Belakang

International Conference on Management (ICOMAN) 2026 membutuhkan website resmi yang berfungsi sebagai:
1. **Media informasi publik** — profil conference, pembicara, jadwal, call for papers, dll.
2. **CMS internal** — panitia dapat mengubah konten tanpa menyentuh kode (via admin panel Filament).

Karena ini conference internasional, asumsi default: website **bilingual (EN/ID)**, dengan EN sebagai bahasa utama. Jika ternyata cukup satu bahasa (EN saja), tinggal hilangkan layer translatable — tidak mengubah struktur besar.

## 2. Tujuan Produk

- Website ringan (fast load, minim JS berat), SEO-friendly, mobile-first.
- Semua konten dinamis dikelola non-developer via admin panel.
- Siap deploy di subdomain universitas (pola sama seperti `manajemen-feb.unm.ac.id`).
- MVP bisa live meski materi belum lengkap — admin bisa isi bertahap.

## 3. Analisis Konten Standar Website Conference Akademik

Berdasarkan pola umum website conference internasional (IEEE, Springer-indexed conference, dsb.), berikut struktur konten yang lazim ditampilkan — ini jadi basis modul CMS:

| # | Bagian | Isi Umum | Wajib MVP? |
|---|--------|----------|------------|
| 1 | **Hero / Home** | Nama conference, tema/tagline, tanggal, lokasi (fisik/hybrid), countdown, CTA (Submit Paper / Register) | Wajib |
| 2 | **About / Overview** | Latar belakang, tujuan, tema tahun ini, penyelenggara (host university) | Wajib |
| 3 | **Committee** | Steering Committee, Organizing Committee, Scientific Committee/Reviewers — nama, afiliasi, foto | Wajib |
| 4 | **Keynote/Invited Speakers** | Foto, nama, gelar, afiliasi, topik bahasan, bio singkat | Wajib |
| 5 | **Call for Papers** | Topik/scope bahasan, jenis submission (full paper/abstract), format submission | Wajib |
| 6 | **Important Dates** | Deadline submission, notification, camera-ready, deadline registrasi, tanggal conference | Wajib |
| 7 | **Registration** | Kategori biaya (presenter/participant, lokal/internasional, early-bird/regular), metode pembayaran | Wajib |
| 8 | **Paper Submission** | Umumnya link keluar ke OJS/EasyChair/Google Form (bukan sistem review sendiri di MVP) | Wajib (sebagai link) |
| 9 | **Author Guidelines / Templates** | Download template paper (docx/latex), panduan format, plagiarism policy | Wajib |
| 10 | **Publication & Indexing** | Jurnal partner, proceeding publisher, indexing (Scopus/SINTA/ISBN/DOI) | Wajib |
| 11 | **Program / Schedule** | Rundown acara per hari, sesi paralel, ruangan | Penting, bisa menyusul |
| 12 | **Venue & Accommodation** | Alamat, peta (embed Google Maps), rekomendasi hotel | Penting |
| 13 | **Sponsors / Partners** | Logo sponsor per tier, co-host institutions, media partner | Nice-to-have |
| 14 | **News / Announcements** | Pengumuman deadline extension, info penting lain | Wajib (untuk update berkala) |
| 15 | **Gallery** | Foto conference tahun sebelumnya (jika ada) | Nice-to-have |
| 16 | **FAQ** | Pertanyaan umum peserta | Nice-to-have |
| 17 | **Contact / Secretariat** | Email, WhatsApp, form kontak | Wajib |

> Karena saat ini belum ada materi, seluruh modul di atas dibuat sebagai **CRUD kosong** di admin — panitia isi bertahap tanpa perlu developer.

## 4. Target Pengguna

- **Panitia/Admin** (via Filament panel): mengisi & update seluruh konten di atas.
- **Calon peserta/pemakalah** (publik): browsing info, submit paper (redirect ke sistem eksternal), registrasi.
- **Superadmin** (Anda): kelola user admin, role, dan setting global.

## 5. Ruang Lingkup MVP

**Keputusan scope (dikonfirmasi user):**
- Bilingual EN/ID — **aktif**.
- Sistem submission & review paper — **dibangun sendiri**, bukan link eksternal.
- Pembayaran registrasi — **mendukung dua jalur**: manual transfer + upload bukti, DAN payment gateway otomatis.

Konsekuensi: MVP ini bukan lagi sekadar "website informasi + CMS", tapi sudah menjadi **sistem manajemen conference** (informasi publik + portal author/peserta + submission-review + registrasi & pembayaran). Detail modul tambahan ada di §7.

**In-scope:**
- Public website (semua modul di tabel §3 dengan status "wajib"/"penting").
- Admin panel Filament untuk semua modul CMS di atas.
- Role & permission (Superadmin, Admin Konten, Reviewer).
- Upload gambar (speaker photo, sponsor logo, gallery) via Spatie Media Library.
- Site settings global (nama conference, logo, warna tema, social media, kontak).
- Bilingual EN/ID untuk seluruh field teks konten.
- SEO basic (meta title/description per halaman, sitemap, OG image).
- **Portal Author/Peserta** (autentikasi publik, terpisah dari admin Filament): register, login, submit paper, lihat status review, unggah camera-ready, isi registrasi & bayar.
- **Sistem Submission & Review**: submit paper (judul, abstrak, topik, file, co-author), penugasan reviewer oleh admin, form review (skor/komentar/rekomendasi), tracking status per paper.
- **Sistem Registrasi & Pembayaran ganda**: peserta pilih kategori biaya → pilih metode bayar (manual transfer + upload bukti, ATAU gateway otomatis) → admin verifikasi (untuk manual) / status otomatis dari webhook (untuk gateway).

**Out-of-scope MVP (fase lanjutan):**
- Sistem sertifikat otomatis (generate PDF nama peserta).
- Multi-conference penuh (arsip ICOMAN tahun-tahun sebelumnya ditampilkan publik) — struktur data sudah siap via `editions`, tapi UI arsip belum dibangun di MVP.
- Video conference/streaming terintegrasi untuk sesi hybrid.

## 7. Detail Tambahan: Submission, Review, Registrasi

### Submission & Review (alur)
1. Author register/login di portal publik.
2. Author submit paper: judul, abstrak (EN wajib, ID opsional), pilih topik, upload file (docx/pdf, max ukuran ditentukan), tambah co-author (nama, email, afiliasi, tandai corresponding author).
3. Sistem generate nomor submission otomatis.
4. Admin/Panitia meninjau daftar submission di Filament, menugaskan 1–2 reviewer per paper (dari daftar reviewer terdaftar).
5. Reviewer login (portal terpisah/role khusus, atau tetap via Filament dengan akses terbatas — diputuskan saat implementasi) → isi form review: skor, komentar untuk author, rekomendasi (accept / minor revision / major revision / reject).
6. Status paper berubah otomatis berdasarkan hasil review (atau manual oleh admin): *submitted → under review → revision required → accepted/rejected*.
7. Jika accepted, author diminta unggah camera-ready version.
8. Notifikasi email dikirim di setiap perubahan status penting (submitted, revision required, accepted, rejected).

### Registrasi & Pembayaran (alur)
1. Peserta (author yang papernya accepted, atau peserta non-presenter) isi form registrasi: pilih kategori biaya (sesuai `registration_fees`).
2. Pilih metode bayar:
   - **Manual**: tampil info rekening tujuan → peserta upload bukti transfer → status `pending_verification` → admin verifikasi manual di Filament → status `paid`.
   - **Gateway**: redirect ke Midtrans/Xendit → setelah bayar, webhook otomatis set status `paid`.
3. Peserta dapat cek status registrasi & bukti bayar di dashboard portalnya.
4. Admin punya dashboard rekap: jumlah peserta per kategori, status pembayaran, total pemasukan (estimasi, bukan akuntansi resmi).

## 6. Non-Functional Requirements

- **Ringan**: skor Lighthouse Performance target ≥ 85 di mobile. Hindari JS framework berat (SPA) — cukup Blade + Alpine.js + Tailwind, image lazy-load & optimized (WebP via media library conversions).
- **Aman**: admin panel di path terpisah, rate-limit login, 2FA opsional untuk superadmin.
- **Mudah dikelola**: field CMS pakai rich text editor ringan (Filament's default, bukan full TinyMCE berat) untuk konten panjang seperti About.
- **Dapat diskalakan ke tahun berikutnya**: struktur data disiapkan agar tahun depan (ICOMAN 2027) bisa reuse dengan minimal effort (lihat konsep `editions` di ARCHITECTURE.md).
