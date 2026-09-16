# ICOMAN 2026

Sistem manajemen International Conference on Management 2026 yang mencakup website publik bilingual, CMS panitia, portal author, submission dan review paper, registrasi, serta pembayaran manual/Kasera Pay.

## Stack

- PHP 8.3 dan Laravel 13
- Filament 4
- Blade, Livewire, Tailwind CSS 4, dan Vite 8
- MySQL/MariaDB untuk production
- Spatie Permission, Media Library, Settings, dan Translatable

## Instalasi lokal

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Admin tersedia di `/admin`, sedangkan portal peserta tersedia di `/author/login`. Akun development tidak disimpan di dokumentasi; buat akun lokal melalui seeder development atau admin panel.

Untuk memproses email/notifikasi asynchronous:

```bash
php artisan queue:work
```

## Pemeriksaan kualitas

```bash
composer test
vendor/bin/pint --test
npm run build
composer audit --locked
npm audit --omit=dev
```

## Konfigurasi production

- Gunakan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- Gunakan database user khusus aplikasi dan password yang kuat.
- Isi kredensial SMTP dan Kasera Pay melalui environment atau Pengaturan admin, bukan repository.
- Ganti `KASERA_API_KEY` ke key `kp_live_` hanya setelah uji dengan key `kp_test_` lulus.
- Arahkan webhook Kasera Pay ke `/payment/kasera/notification` (https, wajib publik).
- Aktifkan queue worker dan scheduler dengan process manager/cron.
- Jalankan `php artisan storage:link` dan `php artisan optimize`.
- Siapkan backup database dan `storage/app/public`, serta uji restore.

Detail produk terdapat di `PRD.md`; status implementasi dan deployment checklist berada di `PROGRESS.md`.
