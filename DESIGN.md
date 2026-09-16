# DESIGN.md — Arahan Desain ICOMAN 2026

> Arahan ini **ditetapkan pemilik situs**, dituliskan dari jawaban atas
> pertanyaan arahan pada 16 September 2026. Isinya keputusan, bukan usulan.
>
> Satu nilai ditandai sebagai turunan, bukan jawaban langsung: lihat dial
> RHYTHM di bawah, dan koreksi bila dugaannya meleset.
>
> Berkas ini sumber arahan, bukan penyaring. Penyaringnya `antislop.md`.

---

## Identitas

**International Conference on Management (ICOMAN) 2026**, konferensi akademik
internasional yang diselenggarakan Fakultas Ekonomi dan Bisnis, Universitas
Negeri Makassar.

Pembacanya dosen, peneliti, dan mahasiswa pascasarjana, sebagian besar dari
Indonesia dan Asia Tenggara, banyak yang membukanya dari ponsel.

## Kesan yang dituju

**"Ini konferensi sungguhan, bukan konferensi predator. Paper saya akan dinilai
dengan benar."**

Itu satu kalimat yang harus ditinggalkan situs ini pada akademisi yang baru
pertama membukanya. Setiap keputusan desain diuji terhadap kalimat itu.

Konsekuensinya tegas, dan beberapa di antaranya berlawanan dengan naluri
membuat situs terlihat menarik:

- **Bukti mengalahkan kesan.** Nama pembicara yang nyata, tenggat yang pasti,
  biaya yang terang, indeksasi yang bisa diperiksa. Semua itu lebih meyakinkan
  daripada tampilan yang mengesankan.
- **Ketenangan adalah sinyal.** Konferensi predator justru terlihat ramai dan
  bersemangat. Menahan diri membedakan kita dari mereka.
- **Tidak ada angka atau klaim tanpa sumber.** Lebih baik kosong daripada
  menimbulkan ragu.

## Kepribadian

**Tenang dan resmi.** Situs ini tidak berusaha memukau. Ia menyampaikan
informasi dengan lugas, seperti situs universitas besar atau lembaga negara,
dan membiarkan isinya yang meyakinkan.

| Ya | Bukan |
|---|---|
| Menahan diri | Berusaha memukau |
| Padat dan bisa dipindai | Lapang demi kesan mewah |
| Formal, jelas | Kaku, birokratis |
| Satu aksen pada saat yang tepat | Aksen di mana-mana |

## Motif identitas

**Warna dan bentuk logo.** Itu satu-satunya penanda visual yang diulang di
seluruh situs, dan ia harus dipakai dengan **lebih tegas dan lebih konsisten**,
bukan sekadar menjadi warna tombol.

Artinya: jingga terakota dan biru tua hadir sebagai pasangan yang dikenali,
dipakai pada tempat yang sama dengan cara yang sama di setiap halaman. Tidak ada
motif kedua. Tidak ada pola latar, tidak ada bentuk hiasan yang tidak berasal
dari logo.

## Palet

| Peran | Nilai | Dipakai untuk |
|---|---|---|
| Brand | `#d9621c` | Bidang: penanda, garis, latar lembut, hiasan |
| Brand (teks) | `--brand-ink` | Teks berwarna merek, dan latar yang memikul teks putih |
| Brand 2 | `#13355c` | Judul, latar gelap, teks berat |

Dua warna, tidak lebih. Warna ketiga hanya masuk bila membawa arti, misalnya
hijau untuk lunas dan merah untuk gagal, bukan sebagai variasi.

Varian `-ink` ada karena warna logo tidak memenuhi ambang keterbacaan sebagai
teks. Nilai cadangan bila Pengaturan dikosongkan tersimpan di
`SiteSettings::DEFAULT_BRAND` dan `DEFAULT_BRAND_2`.

## Tipografi

| Peran | Huruf |
|---|---|
| Judul | **Space Grotesk** |
| Isi | **Instrument Sans** |
| Ukuran dasar | 16px |

Tidak ada huruf monospace di antarmuka publik, dan tidak ada judul serba kapital
dengan jarak huruf lebar.

## Dial

| Dial | Nilai | Dasarnya |
|---|---|---|
| ENERGY | **1** | Dipilih pemilik: tenang dan resmi. |
| RHYTHM | **2** | **Turunan, bukan jawaban langsung.** ENERGY 1 dan kesan "serius dan kredibel" mengarah ke susunan yang tertib dan mudah ditebak. Nilai 2 dipilih agar bagian yang dibaca dan bagian yang dipindai tetap boleh berbeda perataan, karena itu menyangkut keterbacaan. Koreksi ke 1 bila Anda ingin seluruh bagian seragam. |
| MOTION | **2** | Dipilih pemilik: bagian memudar masuk saat digulir. |

## Batasan nyata

Bukan selera, melainkan kenyataan yang membentuk desainnya:

- **Dua bahasa.** Setiap teks tampil dalam Indonesia dan Inggris, dengan panjang
  yang berbeda. Tidak ada tata letak yang bergantung pada jumlah karakter.
- **Isinya dikelola panitia.** Teks, gambar, dan urutan bagian disunting lewat
  panel admin. Desain tidak boleh rusak ketika judul lebih panjang, gambar tidak
  ada, atau sebuah bagian dikosongkan.
- **Bagian halaman dapat disusun ulang.** Beranda dirakit dari blok yang bisa
  ditambah, dipindah, dan dihapus. Tiap blok harus berdiri sendiri.
- **Ponsel lebih dulu.** Sebagian besar peserta membuka dari ponsel, termasuk
  saat membayar.
- **Gerak boleh dimatikan.** MOTION 2 harus menghormati `prefers-reduced-motion`.
