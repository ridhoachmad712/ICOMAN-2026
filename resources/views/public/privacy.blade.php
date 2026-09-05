<x-layout :title="app()->getLocale() === 'id' ? 'Privasi dan penggunaan data' : 'Privacy and data use'">
    @php $id = app()->getLocale() === 'id'; @endphp
    <x-page-header :title="$id ? 'Privasi dan penggunaan data' : 'Privacy and data use'" />
    <article class="prose prose-slate mx-auto max-w-3xl px-4 py-12">
        <p>{{ $id ? 'Halaman ini menjelaskan penggunaan data dalam portal konferensi.' : 'This page describes how data is used in the conference portal.' }}</p>
        <h2>{{ $id ? 'Data yang diproses' : 'Data processed' }}</h2>
        <p>{{ $id ? 'Nama, email, afiliasi, negara, nomor kontak, kategori peserta, data penulis, abstrak, naskah, hasil review, persetujuan syarat, serta catatan registrasi dan pembayaran digunakan untuk mengelola akun dan penyelenggaraan konferensi.' : 'Names, email addresses, affiliations, countries, contact numbers, participant categories, author details, abstracts, manuscripts, reviews, terms acceptance, and registration and payment records are used to operate accounts and organize the conference.' }}</p>
        <h2>{{ $id ? 'Akses dan pembayaran' : 'Access and payment' }}</h2>
        <p>{{ $id ? 'Naskah dapat diakses pemilik, reviewer yang ditugaskan, dan panitia yang berwenang. Pembayaran diproses melalui Midtrans; nama, email, nomor kontak, dan rincian transaksi diteruskan saat checkout. Informasi kartu dimasukkan pada halaman penyedia pembayaran.' : 'Manuscripts are accessible to their owner, assigned reviewers, and authorized committee members. Payments are processed through Midtrans; the name, email, contact number, and transaction details are sent at checkout. Card details are entered on the payment provider’s page.' }}</p>
        <h2>{{ $id ? 'Pertanyaan atau koreksi data' : 'Questions or data corrections' }}</h2>
        <p>{{ $id ? 'Gunakan halaman kontak untuk meminta koreksi data, menanyakan penyimpanan data, atau menyampaikan permintaan penghapusan. Panitia menangani permintaan sesuai kebutuhan administrasi dan pencatatan konferensi.' : 'Use the contact page to request a correction, ask about data retention, or submit a deletion request. The committee handles requests in accordance with conference administration and recordkeeping needs.' }}</p>
        <a href="{{ route('contact') }}">{{ $id ? 'Hubungi panitia' : 'Contact the committee' }}</a>
    </article>
</x-layout>
