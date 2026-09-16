{{--
    Pesan hasil aksi POST di portal author.

    Dibuat setelah tombol pembayaran di dasbor co-host terlihat tidak bereaksi:
    aksinya memang gagal dan mengirim pesan lewat session, tapi halamannya tidak
    pernah menampilkannya — jadi satu-satunya tanda adalah "too many requests"
    setelah ditekan berkali-kali.
--}}
@if(session('error'))
    <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">
        {{ session('error') }}
    </div>
@endif

@if(session('status'))
    <div role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300">
        {{ $errors->first() }}
    </div>
@endif
