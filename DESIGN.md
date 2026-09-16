# DESIGN.md — Arahan Desain ICOMAN 2026

> **Status: DRAFT, diturunkan dari situs yang sudah berjalan, belum dikoreksi pemiliknya.**
>
> Berkas ini dibuat dengan membaca warna, huruf, dan pola yang sudah dipakai
> ICOMAN 2026 hari ini, lalu menuliskannya sebagai arahan. Artinya ia
> mengabadikan keadaan sekarang, termasuk bagian yang mungkin justru ingin Anda
> ubah. Setiap baris di bawah ini boleh dan sebaiknya dikoreksi.
>
> Berkas ini adalah sumber arahan, bukan penyaring. Penyaringnya `antislop.md`.

---

## Identitas

**International Conference on Management (ICOMAN) 2026** — konferensi akademik
internasional yang diselenggarakan Fakultas Ekonomi dan Bisnis, Universitas
Negeri Makassar.

Yang dilayani situs ini tiga hal, berurutan menurut kepentingannya:

1. Meyakinkan akademisi bahwa konferensi ini sungguh-sungguh dan layak diikuti.
2. Menyampaikan tenggat dan biaya tanpa membuat orang harus bertanya.
3. Mengantar orang dari membaca ke mendaftar dan membayar.

Pembacanya dosen, peneliti, dan mahasiswa pascasarjana, sebagian besar dari
Indonesia dan Asia Tenggara, banyak yang membukanya dari ponsel.

## Kepribadian

Yang dituju: **resmi tapi tidak kaku, jelas, dan bisa dipercaya.** Ini situs
institusi pendidikan negeri, bukan produk teknologi. Kredibilitas lebih penting
daripada kesan mutakhir.

| Ya | Bukan |
|---|---|
| Tenang, rapi, mudah dipindai | Ramai, penuh efek |
| Akademik, sopan | Kaku, birokratis |
| Hangat lewat satu aksen | Warna-warni |
| Ringkas | Bertele-tele |

## Palet

Diambil dari nilai yang benar-benar tersimpan di Pengaturan situs.

| Peran | Nilai | Dipakai untuk |
|---|---|---|
| Brand (aksen) | `#d9621c` | Tombol utama, tautan, penanda aktif, garis penekanan |
| Brand 2 (dasar) | `#13355c` | Judul, latar gelap, teks berat |
| Aksen hangat | `#f26522` | Sorotan kecil, diambil dari logo |
| Aksen kuat | `#d84c12` | Keadaan hover tombol aksen |

Jingga terakota berasal dari logo konferensi; biru tua menyeimbangkannya dan
memberi kesan resmi. Dua warna itu sudah cukup: warna ketiga hanya boleh masuk
kalau ada arti yang dibawanya (misalnya hijau untuk lunas, merah untuk gagal),
bukan sebagai variasi.

> **Catatan koreksi:** tata letak publik masih memakai biru `#1d4ed8` sebagai
> nilai cadangan bila Pengaturan kosong, sementara portal author memakai jingga
> `#d9621c`. Keduanya berasal dari identitas yang berbeda. Yang benar hanya satu,
> dan pemiliknya perlu menentukan.

## Tipografi

| Peran | Huruf | Alasan |
|---|---|---|
| Judul | **Space Grotesk** | Geometris dengan sedikit karakter, membedakan judul dari isi tanpa terasa dekoratif |
| Isi | **Instrument Sans** | Terbaca pada ukuran kecil, netral, tidak melawan judulnya |
| Ukuran dasar | 16px | |

Judul dipasang dengan `letter-spacing: -0.02em` supaya rapat dan tegas. Tidak ada
huruf monospace di antarmuka publik, dan tidak ada judul serba kapital dengan
jarak huruf lebar.

## Suasana

Terang sebagai dasar. Latar putih dengan bagian ber-tint lembut warna brand untuk
memisahkan bagian, bukan hitam-sebagai-gaya. Sudut membulat sedang, bayangan
tipis dan hanya pada elemen yang memang terangkat (kartu yang bisa diklik),
bukan pada segala sesuatu.

Situs ini dibaca orang yang sedang mencari informasi, bukan sedang dibujuk.
Ruang kosong dipakai untuk memudahkan membaca, bukan untuk kesan mewah.

## Dial

Skala 1 sampai 5, sesuai kerangka `antislop.md` Part 3.

| Dial | Nilai | Maksudnya |
|---|---|---|
| ENERGY | **2** | Tenang. Satu aksen hangat sebagai penekanan, sisanya menahan diri. |
| RHYTHM | **3** | Bagian-bagiannya perlu berbeda susunan, tidak semuanya judul-tengah lalu grid. |
| MOTION | **1** | Gerak seperlunya. Konferensi akademik tidak butuh animasi masuk di tiap bagian. |

## Batasan nyata

Hal-hal berikut bukan selera, melainkan kenyataan yang membentuk desainnya:

- **Dua bahasa.** Setiap teks tampil dalam Indonesia dan Inggris. Panjangnya
  berbeda, jadi tidak ada tata letak yang bergantung pada jumlah karakter.
- **Isinya dikelola panitia.** Hampir semua teks, gambar, dan urutan bagian
  disunting lewat panel admin. Desain tidak boleh rusak ketika judul lebih
  panjang, gambar tidak ada, atau sebuah bagian dikosongkan.
- **Bagian halaman dapat disusun ulang.** Beranda dirakit dari blok yang bisa
  ditambah, dipindah, dan dihapus admin. Tiap blok harus berdiri sendiri.
- **Ponsel lebih dulu.** Sebagian besar peserta membuka dari ponsel, termasuk
  saat membayar.
