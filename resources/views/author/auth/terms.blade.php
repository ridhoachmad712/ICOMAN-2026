<x-author-layout :title="app()->getLocale() === 'id' ? 'Syarat & Ketentuan' : 'Terms & Conditions'">
    @php
        $isId = app()->getLocale() === 'id';
        $roleLabel = $role === 'presenter' ? 'Presenter' : ($isId ? 'Peserta Seminar' : 'Seminar Attendee');
    @endphp

    <div class="mx-auto max-w-2xl">
        <div class="card p-6 sm:p-8">
            {{-- Header --}}
            <div class="mb-6 text-center">
                <span class="text-xs font-semibold uppercase tracking-widest text-[var(--brand)]">
                    {{ siteSettings()->conference_name ?: 'ICOMAN 2026' }}
                </span>
                <h1 class="mt-2 font-display text-2xl font-bold tracking-tight text-[var(--brand-2)]">
                    {{ $isId ? 'Syarat & Ketentuan' : 'Terms & Conditions' }}
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    <span class="chip">{{ $roleLabel }}</span>
                </p>
            </div>

            {{-- Isi T&C --}}
            <div class="space-y-4 text-sm leading-relaxed text-slate-600 [&_strong]:text-slate-900">
                @if($role === 'presenter')
                    <p><strong>1. Persetujuan.</strong> Dengan mendaftar sebagai presenter ICOMAN 2026, Anda menyatakan telah membaca, memahami, dan menyetujui seluruh syarat & ketentuan ini.</p>
                    <div>
                        <strong>2. Tahapan keikutsertaan.</strong> Keikutsertaan presenter mengikuti tahapan berikut:
                        <ol class="mt-1.5 list-decimal space-y-1 pl-5">
                            <li>Membuat akun;</li>
                            <li>Mengirim <strong>abstrak</strong>;</li>
                            <li><strong>Review abstrak</strong> oleh reviewer;</li>
                            <li>Reviewer menentukan <strong>rekomendasi jalur publikasi</strong> naskah;</li>
                            <li><strong>Author memilih opsi jurnal</strong> — apabila naskah <strong>direkomendasikan</strong> untuk opsi publikasi lanjutan, tersedia <strong>dua opsi</strong>, salah satunya dengan <strong>biaya penerbitan tambahan</strong>;</li>
                            <li><strong>Letter of Acceptance (LOA) dan tagihan pembayaran diterbitkan</strong> sesuai pilihan Anda;</li>
                            <li>Setelah pembayaran terverifikasi, presenter <strong>mengirimkan naskah lengkap (full paper)</strong>.</li>
                        </ol>
                    </div>
                    <p><strong>3. Data pendaftaran.</strong> Anda menjamin data yang diisi benar dan akurat. Satu akun digunakan untuk satu orang.</p>
                    <p><strong>4. Keaslian & etika ilmiah.</strong> Abstrak maupun full paper adalah karya asli, bebas plagiarisme, fabrikasi, dan falsifikasi data, serta belum/tidak sedang dalam proses review atau publikasi pada forum/jurnal lain. Anda bertanggung jawab atas izin penggunaan data dan persetujuan seluruh penulis (co-author).</p>
                    <p><strong>5. Abstrak.</strong> Abstrak ditulis dalam <strong>Bahasa Inggris, 150–500 kata</strong>, sesuai topik konferensi, dan Anda bersedia merevisi bila diminta reviewer.</p>
                    <p><strong>6. Review & rekomendasi publikasi.</strong> Abstrak dinilai oleh reviewer. Selain menentukan <strong>diterima/revisi/ditolak</strong>, reviewer juga menentukan apakah naskah <strong>direkomendasikan untuk opsi publikasi lanjutan</strong>. Seluruh keputusan reviewer/panitia bersifat <strong>final dan tidak dapat diganggu gugat</strong>.</p>
                    <p><strong>7. Pilihan opsi publikasi.</strong> Apabila reviewer <strong>merekomendasikan</strong> naskah untuk opsi publikasi lanjutan, Anda dapat memilih di antara <strong>dua opsi jurnal</strong>: <strong>(a) opsi publikasi standar</strong> — tanpa biaya tambahan, atau <strong>(b) opsi publikasi lanjutan</strong> — dengan <strong>biaya penerbitan tambahan sebesar Rp 300.000</strong>. Apabila naskah <strong>tidak direkomendasikan</strong> untuk opsi lanjutan, keikutsertaan mengikuti opsi publikasi standar. Pilihan Anda menentukan besaran tagihan pada LOA.</p>
                    <p><strong>8. LOA & pembayaran.</strong> Setelah Anda menentukan pilihan pada butir 7, <strong>LOA dan tagihan pembayaran diterbitkan</strong>. Total tagihan mengikuti kategori, periode (early-bird/reguler), dan pilihan opsi publikasi (termasuk tambahan Rp 300.000 bila memilih opsi lanjutan). Pendaftaran/penerbitan dianggap sah setelah pembayaran <strong>terverifikasi</strong>. <strong>Biaya yang telah dibayarkan tidak dapat dikembalikan (non-refundable).</strong></p>
                    <p><strong>9. Pengiriman full paper.</strong> Setelah pembayaran terverifikasi, Anda <strong>wajib mengirimkan naskah lengkap (full paper)</strong> sesuai <strong>template dan tenggat</strong> yang ditetapkan panitia. Full paper inilah yang menjadi dasar publikasi, dan panitia dapat meminta perbaikan (revisi) sebelum publikasi. <strong>Keterlambatan atau tidak mengirimkan full paper mengakibatkan gugurnya hak publikasi tanpa pengembalian biaya</strong>, sementara status keikutsertaan/kehadiran Anda tetap berlaku.</p>
                    <p><strong>10. Kewajiban presentasi.</strong> Presenter wajib hadir dan mempresentasikan karyanya sesuai jadwal (daring melalui Zoom) serta menjaga ketertiban sesi. Ketidakhadiran tanpa pemberitahuan tidak menggugurkan kewajiban yang telah timbul.</p>
                    <p><strong>11. Penerbitan & lisensi.</strong> Anda memberi izin kepada panitia/penerbit untuk memproses abstrak dan full paper guna keperluan penerbitan dan pengindeksan, serta menjamin naskah tidak melanggar hak kekayaan intelektual pihak lain.</p>
                    <p><strong>12. Pernyataan kategori.</strong> Anda menyatakan kategori yang dipilih benar. <strong>Mahasiswa S1</strong> hanya untuk yang sedang menempuh program sarjana (S1); mahasiswa S2/S3 dan umum memilih <strong>Dosen/Umum</strong>; peserta dari luar negeri memilih <strong>International</strong>. Panitia berhak meminta bukti (mis. Kartu Tanda Mahasiswa) dan menyesuaikan tarif bila kategori tidak sesuai.</p>
                    <p><strong>13. Sertifikat.</strong> Sertifikat presenter diberikan kepada peserta yang menyelesaikan presentasi, pembayaran, dan pengiriman full paper.</p>
                    <p><strong>14. Kode etik.</strong> Dilarang melakukan pelecehan, ujaran kebencian, atau mengganggu jalannya acara. Pelanggaran dapat mengakibatkan pembatalan keikutsertaan tanpa pengembalian biaya.</p>
                    <p><strong>15. Data pribadi.</strong> Data Anda dikumpulkan dan digunakan untuk keperluan penyelenggaraan acara sesuai kebijakan privasi panitia.</p>
                    <p><strong>16. Perubahan & keadaan kahar.</strong> Panitia dapat mengubah jadwal, susunan acara, atau format bila diperlukan, termasuk karena keadaan di luar kendali (force majeure).</p>
                    <p><strong>17. Hak panitia.</strong> Panitia berhak membatalkan pendaftaran yang melanggar ketentuan atau memberikan informasi tidak benar.</p>
                @else
                    <p><strong>1. Persetujuan.</strong> Dengan mendaftar sebagai peserta seminar ICOMAN 2026, Anda menyatakan telah membaca, memahami, dan menyetujui seluruh syarat & ketentuan ini.</p>
                    <p><strong>2. Data pendaftaran.</strong> Anda menjamin data yang diisi benar dan akurat. Satu akun untuk satu orang.</p>
                    <p><strong>3. Pembayaran.</strong> Biaya registrasi mengikuti kategori dan periode (early-bird/reguler). Pendaftaran sah setelah pembayaran <strong>terverifikasi</strong>. <strong>Biaya yang telah dibayarkan tidak dapat dikembalikan (non-refundable).</strong></p>
                    <p><strong>4. Akses seminar.</strong> Tautan/akses Zoom bersifat <strong>pribadi</strong> dan tidak untuk dibagikan atau dipindahtangankan kepada pihak lain.</p>
                    <p><strong>5. Rekaman & distribusi.</strong> Dilarang merekam, menyebarluaskan, atau menggunakan ulang materi/presentasi acara tanpa izin tertulis dari panitia dan pemilik materi.</p>
                    <p><strong>6. Pernyataan kategori.</strong> Anda menyatakan kategori yang dipilih benar (<strong>Mahasiswa S1</strong> hanya untuk program sarjana; S2/S3 dan umum memilih <strong>Dosen/Umum</strong>; peserta luar negeri memilih <strong>International</strong>). Panitia berhak meminta bukti dan menyesuaikan tarif bila tidak sesuai.</p>
                    <p><strong>7. Sertifikat.</strong> E-certificate kehadiran diberikan kepada peserta terdaftar yang <strong>mengikuti sesi utama (Keynote & Plenary)</strong>.</p>
                    <p><strong>8. Kode etik.</strong> Peserta menjaga ketertiban acara. Pelanggaran dapat mengakibatkan dikeluarkan dari acara tanpa pengembalian biaya.</p>
                    <p><strong>9. Data pribadi.</strong> Data digunakan untuk keperluan acara sesuai kebijakan privasi panitia.</p>
                    <p><strong>10. Perubahan & keadaan kahar.</strong> Panitia dapat mengubah jadwal/format bila diperlukan, termasuk karena keadaan di luar kendali.</p>
                    <p><strong>11. Hak panitia.</strong> Panitia berhak membatalkan pendaftaran yang melanggar ketentuan atau memberikan informasi tidak benar.</p>
                @endif
            </div>

            {{-- Aksi --}}
            <div class="mt-7 border-t border-slate-100 pt-6">
                <p class="mb-4 text-center text-xs text-slate-500">
                    {{ $isId ? 'Dengan menekan tombol di bawah, Anda menyetujui seluruh Syarat & Ketentuan di atas.' : 'By clicking the button below, you agree to all the Terms & Conditions above.' }}
                </p>
                <form method="POST" action="{{ route('author.register.accept-terms') }}">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}">
                    <button type="submit" class="btn btn-accent w-full">
                        {{ $isId ? 'Setuju & Lanjutkan' : 'Accept & Continue' }} →
                    </button>
                </form>
                <a href="{{ route('author.register') }}" class="mt-3 block text-center text-xs font-medium text-slate-500 hover:text-slate-700">
                    ← {{ $isId ? 'Kembali pilih peran' : 'Back to role selection' }}
                </a>
            </div>
        </div>
    </div>
</x-author-layout>
