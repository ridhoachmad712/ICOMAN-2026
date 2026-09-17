<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * Membatalkan order pembayaran yang tertinggal dari gateway sebelumnya.
 *
 * Saat gateway berganti, order yang masih berstatus `initiated` tidak ikut
 * berpindah. Barisnya tetap menyimpan tautan checkout gateway lama, dan selama
 * masih ada, author bisa kembali diarahkan ke sana — membayar ke tempat yang
 * statusnya tidak pernah kita tanyakan lagi. Baris seperti itu juga menahan
 * pilihan jurnal dan voucher, karena dianggap pembayaran yang belum selesai.
 *
 * Yang dibatalkan HANYA order yang belum dibayar. Pembayaran berstatus
 * `success` tidak pernah disentuh: itu uang yang benar-benar masuk dan menjadi
 * catatan yang harus tetap utuh, apa pun gateway yang menerimanya.
 */
class RetireOldGatewayPayments extends Command
{
    protected $signature = 'icoman:retire-old-payments
        {--gateway=borderpay : Nama gateway yang sedang dipakai; selain ini dianggap warisan}
        {--fix : Terapkan perubahannya; tanpa ini hanya melaporkan}';

    protected $description = 'Batalkan order pembayaran yang belum dibayar dan tertinggal dari gateway sebelumnya.';

    public function handle(): int
    {
        $current = (string) $this->option('gateway');
        $apply = (bool) $this->option('fix');

        $stale = Payment::query()
            ->where('method', 'gateway')
            ->where('status', 'initiated')
            ->where(fn ($query) => $query->where('gateway_name', '!=', $current)->orWhereNull('gateway_name'))
            ->with('registration.author')
            ->orderBy('id')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Tidak ada order tertinggal dari gateway sebelumnya.');

            return self::SUCCESS;
        }

        $this->line($stale->count().' order belum dibayar milik gateway sebelumnya:');

        foreach ($stale as $payment) {
            $this->line(sprintf(
                '  #%s — %s — Rp %s — %s — %s',
                str_pad((string) ($payment->registration_id ?? 0), 5, '0', STR_PAD_LEFT),
                $payment->registration?->author?->name ?? 'tanpa author',
                number_format((float) $payment->amount, 0, ',', '.'),
                $payment->gateway_name ?? 'tanpa nama gateway',
                $payment->checkout_url ?? 'tanpa tautan checkout',
            ));
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Belum ada yang diubah. Jalankan ulang dengan --fix untuk membatalkannya.');

            return self::SUCCESS;
        }

        // Dibatalkan satu per satu, bukan lewat satu UPDATE massal, supaya
        // setiap baris melewati model dan event yang menyertainya.
        foreach ($stale as $payment) {
            $payment->update(['status' => 'failed']);
        }

        $this->newLine();
        $this->info($stale->count().' order dibatalkan. Author akan mendapat halaman bayar baru saat menekan bayar lagi.');

        $paid = Payment::query()
            ->where('method', 'gateway')
            ->where('status', 'success')
            ->where(fn ($query) => $query->where('gateway_name', '!=', $current)->orWhereNull('gateway_name'))
            ->count();

        if ($paid > 0) {
            $this->line($paid.' pembayaran lunas dari gateway sebelumnya dibiarkan apa adanya sebagai catatan.');
        }

        return self::SUCCESS;
    }
}
