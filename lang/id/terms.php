<?php

return json_decode(<<<'JSON'
{
  "common": [
    {
      "title": "Persetujuan",
      "body": "Dengan mendaftar, Anda menyatakan telah membaca, memahami, dan menyetujui syarat keikutsertaan ini."
    },
    {
      "title": "Data pendaftaran",
      "body": "Isi data yang benar dan akurat. Satu akun untuk satu orang. Mahasiswa S1 memilih kategori S1; mahasiswa S2/S3 dan umum memilih Umum; peserta luar negeri memilih Internasional. Panitia dapat meminta bukti kategori dan menyesuaikan tarif bila tidak sesuai."
    },
    {
      "title": "Pembayaran",
      "body": "Biaya mengikuti kategori yang dipilih, ditambah opsi publikasi jika berlaku. Periksa rincian dan total invoice sebelum membayar. Untuk harga USD, invoice mencantumkan kurs tetap dan total penagihan dalam IDR. Registrasi sah setelah pembayaran terverifikasi. Biaya yang telah dibayarkan tidak dapat dikembalikan."
    },
    {
      "title": "Akses acara dan materi",
      "body": "Akses Zoom bersifat pribadi. Dilarang membagikan akses, merekam, atau menyebarluaskan materi tanpa izin tertulis panitia dan pemilik materi."
    },
    {
      "title": "Kode etik",
      "body": "Dilarang melakukan pelecehan, ujaran kebencian, atau mengganggu acara. Pelanggaran dapat mengakibatkan pembatalan keikutsertaan tanpa pengembalian biaya."
    },
    {
      "title": "Penggunaan data",
      "body": "Data digunakan untuk pengelolaan akun, review, registrasi, pembayaran, dan penyelenggaraan konferensi. Baca halaman Privasi dan penggunaan data untuk penjelasan dan kontak panitia."
    },
    {
      "title": "Perubahan dan hak panitia",
      "body": "Panitia dapat mengubah jadwal, susunan acara, atau format jika diperlukan, termasuk keadaan kahar. Panitia berhak membatalkan pendaftaran yang melanggar ketentuan atau memberikan informasi tidak benar."
    }
  ],
  "presenter": [
    {
      "title": "Tahapan presenter",
      "body": "Kirim abstrak, jalani review dan revisi jika diminta. Keputusan diterima oleh panitia menerbitkan LOA otomatis. Setelah itu, pilih opsi jurnal jika ditawarkan, periksa invoice, bayar, lalu unggah full paper."
    },
    {
      "title": "Keaslian dan etika ilmiah",
      "body": "Abstrak dan full paper harus karya asli, bebas plagiarisme, fabrikasi, dan falsifikasi data, serta tidak sedang direview atau diterbitkan pada forum lain. Anda bertanggung jawab atas izin data dan persetujuan seluruh co-author."
    },
    {
      "title": "Abstrak dan review",
      "body": "Abstrak wajib bahasa Inggris, 150–500 kata. Reviewer menilai dan memberi rekomendasi; panitia menetapkan keputusan diterima, revisi, atau ditolak. Keputusan reviewer/panitia bersifat final."
    },
    {
      "title": "Pilihan publikasi",
      "body": "Jika direkomendasikan reviewer, opsi SINTA 3 tersedia dengan biaya tambahan yang ditampilkan pada invoice. Opsi reguler tidak menambah biaya. Pilihan dilakukan setelah LOA terbit, sebelum transaksi dimulai. Rekomendasi bukan jaminan penerbitan; naskah mengikuti proses editorial jurnal tujuan."
    },
    {
      "title": "Full paper dan presentasi",
      "body": "Setelah pembayaran, unggah full paper sesuai panduan dan tenggat yang diumumkan. Panitia dapat meminta revisi. Keterlambatan atau tidak mengirim full paper menggugurkan hak publikasi tanpa pengembalian biaya; keikutsertaan tetap berlaku. Presenter wajib hadir dan mempresentasikan naskah sesuai jadwal."
    },
    {
      "title": "Penerbitan dan sertifikat",
      "body": "Anda memberi izin pemrosesan naskah untuk penerbitan dan pengindeksan serta menjamin tidak ada pelanggaran hak kekayaan intelektual pihak lain. Sertifikat presenter diberikan setelah pembayaran, presentasi, dan pengiriman full paper selesai."
    }
  ],
  "participant": [
    {
      "title": "Sertifikat peserta",
      "body": "E-certificate diberikan kepada peserta terdaftar yang mengikuti sesi utama Keynote dan Plenary."
    }
  ]
}
JSON, true);
