# Checklist Konten & Aset — ICOMAN 2026

Panduan mengisi konten via panel admin (`/admin`). Website tetap live walau belum lengkap
(section otomatis tersembunyi jika kosong), tapi tampilan **jauh lebih baik** setelah aset asli diunggah.

## 🔴 Prioritas 1 — Identitas & Hero (paling terlihat)
Menu **Site Settings**:
- [ ] **Logo** — PNG transparan, tinggi ±72px (mis. 240×72). Muncul di navbar; jika kosong pakai teks nama.
- [ ] **Favicon** — PNG/SVG persegi 64×64.
- [ ] **Gambar Hero** — foto lanskap venue/kota, **1600×900** (16:9), < 500KB. Jika kosong pakai pola SVG default.
- [ ] **Warna brand** — Primary & Secondary (saat ini oranye #d9621c + navy #18315e).
- [ ] **Lokasi Acara**, **Format Acara** (mis. "Hybrid (Onsite & Online)").
- [ ] **Nama & Logo Penyelenggara** (host university).
- [ ] **Kontak**: email, WhatsApp, alamat, Google Maps embed URL.
- [ ] **Rekening manual**: nama bank, no. rekening, atas nama (untuk pembayaran transfer).
- [ ] **Default locale** (en/id) & **Social media**.

## 🔴 Prioritas 1 — Edition
Menu **Editions** (sudah ada 1 "ICOMAN 2026", is_active):
- [ ] Tema/tagline (EN & ID), **tanggal mulai & selesai** (mengaktifkan countdown & JSON-LD).

## 🟠 Prioritas 2 — Konten inti homepage
- [ ] **Speakers** — foto **persegi 500×500** (di-crop otomatis ke WebP), nama (tanpa gelar), gelar terpisah, afiliasi, **negara** (dropdown → bendera), topik (EN/ID), bio.
- [ ] **Topics** (Call for Papers) — daftar topik (EN/ID).
- [ ] **Important Dates** — label (EN/ID) + tanggal; tandai `is_highlighted` untuk yang penting.
- [ ] **Registration Fees** — kategori (EN/ID), harga early-bird & regular, catatan.
- [ ] **Pages**: `about`, `venue`, `publication` (indexing Scopus/SINTA/ISBN/DOI) — judul & konten (EN/ID), set **Published**.

## 🟡 Prioritas 3 — Pelengkap
- [ ] **Committees** — foto persegi 200×200, nama, jabatan (EN/ID), afiliasi, kategori.
- [ ] **Downloads** — template/panduan (PDF/DOCX).
- [ ] **Schedules** — rundown per hari.
- [ ] **Sponsors** — logo PNG transparan (lebar ±280px), pilih tier.
- [ ] **News** — thumbnail **16:9 (800×450)**, judul/excerpt/konten (EN/ID), `published_at`, Published.
- [ ] **Galleries** — foto persegi (di-crop 400×400 / 700×500).
- [ ] **FAQs** — pertanyaan & jawaban (EN/ID).

## 🟢 Prioritas 4 — Operasional (sebelum go-live)
- [ ] Akun **Reviewer** (dosen internal) via user admin + role `reviewer`.
- [ ] Kredensial **Midtrans** di `.env` (sandbox → production) + set URL notifikasi webhook.
- [ ] Ganti password superadmin sementara.

---

**Catatan gambar:** semua foto otomatis dikonversi ke **WebP** + di-resize (thumb/card) via Media Library,
jadi unggah kualitas baik (≤ ~1MB) — sistem yang mengoptimalkan. Rasio disarankan:
speaker/gallery = **persegi**, hero/news = **16:9**, logo = **transparan**.
