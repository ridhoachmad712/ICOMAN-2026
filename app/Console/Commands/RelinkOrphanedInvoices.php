<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\Submission;
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
 * Invoice lunas yang tertinggal juga membuat author tidak bisa mengunggah full
 * paper, karena syaratnya melihat invoice milik paper. Invoice seperti itu hanya
 * disambungkan bila akunnya punya tepat satu invoice lunas tanpa paper dan satu
 * paper diterima yang belum lunas.
 *
 * Pembuat invoice otomatis kini mengambil alih invoice semacam itu dengan
 * sendirinya, tapi hanya saat author melewati halaman checkout lagi atau LOA
 * terbit. Perintah ini membereskan yang sudah telanjur.
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

            $paid = $orphan->status === 'paid';
            $paper = $paid ? $this->paperForPaidInvoice($orphan) : $orphan->author?->submissions()
                ->where('edition_id', $orphan->edition_id)
                ->where('status', 'accepted')
                ->whereNotNull('loa_issued_at')
                // Paper yang sudah punya invoicenya sendiri tidak diambil.
                ->whereDoesntHave('registrations')
                ->latest('submitted_at')
                ->first();

            if (! $paper) {
                $this->line('  <fg=yellow>LEWAT</> '.$label.($paid
                    ? ' (lunas) — tidak ada satu paper diterima yang belum lunas untuk invoice ini.'
                    : ' — tidak ada paper diterima yang belum punya invoice.'));
                $stuck++;

                continue;
            }

            $this->line('  <fg=green>SAMBUNG</> '.$label.($paid ? ' (lunas)' : '').' → '.$paper->submission_number);

            if ($paid) {
                // Tagihan kedua untuk paper itu tidak dihapus otomatis: panitia yang memutuskan.
                foreach ($paper->registrations()->where('status', '!=', 'paid')->pluck('id') as $id) {
                    $this->line('    <fg=yellow>PERIKSA</> invoice #'.str_pad((string) $id, 5, '0', STR_PAD_LEFT).' milik paper ini belum lunas dan kemungkinan tagihan ganda.');
                }
            }

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

    private function paperForPaidInvoice(Registration $invoice): ?Submission
    {
        $paper = $invoice->author?->submissions()
            ->where('edition_id', $invoice->edition_id)
            ->where('status', 'accepted')
            ->whereNotNull('loa_issued_at')
            ->latest('submitted_at')
            ->first();

        return $paper?->paidInvoiceToAdopt()?->is($invoice) ? $paper : null;
    }
}
