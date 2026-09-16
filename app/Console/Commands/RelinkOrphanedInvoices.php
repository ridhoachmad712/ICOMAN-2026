<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;

/**
 * Menyambungkan kembali invoice presenter yang kehilangan tautan papernya.
 *
 * `registrations.submission_id` memakai nullOnDelete, jadi paper yang dihapus
 * lalu dikirim ulang meninggalkan invoicenya tanpa paper. Invoice seperti itu
 * tidak akan pernah menampilkan pilihan Jurnal SINTA 3 — tawarannya melekat
 * pada paper, bukan pada invoice — dan diam-diam mengundang tagihan kedua untuk
 * orang yang sama.
 *
 * Pembuat invoice otomatis kini mengambil alih invoice semacam itu dengan
 * sendirinya, tapi hanya saat author melewati halaman checkout lagi. Perintah
 * ini membereskan yang sudah telanjur.
 */
class RelinkOrphanedInvoices extends Command
{
    protected $signature = 'icoman:relink-invoices {--fix : Terapkan perubahannya; tanpa ini hanya melaporkan}';

    protected $description = 'Sambungkan kembali invoice presenter yang kehilangan tautan papernya.';

    public function handle(): int
    {
        $apply = (bool) $this->option('fix');

        $orphans = Registration::query()
            ->whereNull('submission_id')
            ->where('status', '!=', 'paid')
            ->whereHas('registrationFee', fn ($fee) => $fee->where('audience', 'presenter'))
            ->with('author')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Tidak ada invoice presenter yang kehilangan tautan papernya.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $stuck = 0;

        foreach ($orphans as $orphan) {
            $label = '#'.str_pad((string) $orphan->id, 5, '0', STR_PAD_LEFT).' — '.($orphan->author?->name ?? 'tanpa author');

            $paper = $orphan->author?->submissions()
                ->where('edition_id', $orphan->edition_id)
                ->where('status', 'accepted')
                ->whereNotNull('loa_issued_at')
                // Paper yang sudah punya invoicenya sendiri tidak diambil.
                ->whereDoesntHave('registrations')
                ->latest('submitted_at')
                ->first();

            if (! $paper) {
                $this->line('  <fg=yellow>LEWAT</> '.$label.' — tidak ada paper diterima yang belum punya invoice.');
                $stuck++;

                continue;
            }

            $this->line('  <fg=green>SAMBUNG</> '.$label.' → '.$paper->submission_number);

            if ($apply) {
                $orphan->update(['submission_id' => $paper->id]);
            }

            $fixed++;
        }

        $this->newLine();

        if ($stuck > 0) {
            $this->line($stuck.' invoice tidak bisa disambungkan otomatis — periksa manual bersama panitia.');
        }

        if ($fixed === 0) {
            return self::SUCCESS;
        }

        $this->line($apply
            ? $fixed.' invoice disambungkan kembali.'
            : $fixed.' invoice bisa disambungkan. Jalankan ulang dengan --fix untuk menerapkannya.');

        return self::SUCCESS;
    }
}
