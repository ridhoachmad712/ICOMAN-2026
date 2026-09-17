<?php

namespace App\Console\Commands;

use App\Models\Registration;
use Illuminate\Console\Command;

/**
 * Menjelaskan sebuah invoice: kenapa pilihan jurnal muncul atau tidak, dan
 * kenapa cicilan ditawarkan atau tidak.
 *
 * Dibuat setelah sebuah invoice presenter mahasiswa tampil tanpa pilihan
 * SINTA 3. Syaratnya tersebar di beberapa model, jadi menebaknya dari tampilan
 * saja memakan waktu; perintah ini memeriksa tiap syarat satu per satu dan
 * menyebut mana yang tidak terpenuhi.
 */
class DiagnoseInvoice extends Command
{
    protected $signature = 'icoman:diagnose-invoice {invoice : Nomor invoice (id registrasi)}';

    protected $description = 'Periksa satu invoice: syarat pilihan jurnal SINTA 3 dan opsi cicilan.';

    public function handle(): int
    {
        $registration = Registration::with(['author', 'registrationFee', 'submission.reviewAssignments.review'])
            ->find((int) $this->argument('invoice'));

        if (! $registration) {
            $this->error('Invoice #'.$this->argument('invoice').' tidak ditemukan.');

            return self::FAILURE;
        }

        $this->section('Invoice');
        $this->pair('Nomor', '#'.str_pad((string) $registration->id, 5, '0', STR_PAD_LEFT));
        $this->pair('Status', $registration->status);
        $this->pair('Total', 'IDR '.number_format((float) $registration->amount, 0, ',', '.'));
        $this->pair('Sudah dibayar', 'IDR '.number_format($registration->paidAmount(), 0, ',', '.'));

        $author = $registration->author;
        $this->section('Author');
        $this->pair('Nama', $author?->name ?? '—');
        $this->pair('Jalur', $author?->participation_type ?? '(belum dipilih)');
        $this->pair('Kategori', $author?->registrant_category ?? '(belum dipilih)');

        $fee = $registration->registrationFee;
        $this->section('Tarif');
        $this->pair('Audience', $fee?->audience ?? '—');
        $this->pair('Kategori tarif', $fee?->registrant_category ?? '—');
        $this->pair('Harga', 'IDR '.number_format((float) ($fee?->price_regular ?? 0), 0, ',', '.'));
        $this->pair('Cicilan pertama', $fee?->installment_first_amount ? 'IDR '.number_format((float) $fee->installment_first_amount, 0, ',', '.') : '(kosong)');
        $this->pair('Cicilan pertama SINTA 3', $fee?->installment_first_amount_sinta3 ? 'IDR '.number_format((float) $fee->installment_first_amount_sinta3, 0, ',', '.') : '(kosong)');

        $submission = $registration->submission;
        $this->section('Paper yang ditagihkan');

        if (! $submission) {
            $this->line('  <fg=red>Invoice ini TIDAK terhubung ke paper mana pun (submission_id kosong).</>');
            $this->line('  Pilihan jurnal tidak akan pernah muncul pada invoice seperti ini,');
            $this->line('  karena tawaran SINTA 3 melekat pada papernya, bukan pada invoicenya.');

            if ($author && $author->submissions()->where('status', 'accepted')->exists()) {
                $this->line('  <fg=yellow>Author ini punya paper yang sudah diterima, tetapi invoice ini bukan untuk paper itu.</>');
                $this->line('  Periksa apakah ada invoice lain untuk papernya.');
            }
        } else {
            $this->pair('Nomor', $submission->submission_number);
            $this->pair('Status', $submission->status);
            $this->pair('LOA terbit', $submission->loa_issued_at?->format('d M Y H:i') ?? '(belum)');
            $this->pair('Target jurnal', $submission->journal_target ?? 'regular');
            $this->pair('Direkomendasikan reviewer', $submission->reviewsRecommendSinta3() ? 'YA' : 'tidak');
            $this->pair('Tawaran SINTA 3 terbuka', $submission->sinta3_offered ? 'YA' : 'tidak');
            $this->pair('Ditetapkan panitia', $submission->sinta3_offer_overridden_at?->format('d M Y H:i') ?? '(tidak, ikut reviewer)');
        }

        $price = $registration->priceDetails();

        $this->section('Syarat pilihan jurnal di halaman pembayaran');
        $this->check('Invoice terhubung ke paper', $submission !== null);
        $this->check('Tawaran SINTA 3 terbuka pada papernya', (bool) $submission?->sinta3_offered);
        $this->check('Invoice bukan arsip lama', ! ($price['legacy'] ?? false));
        $this->check('Tidak ada pembayaran yang menggantung', ! $registration->hasUnresolvedPayment());
        $this->check('Status invoice pending atau failed', $registration->isPayable());

        $this->section('Syarat opsi cicilan');
        $this->check('Tarif mengizinkan cicilan (presenter + mahasiswa S1, nominal terisi)', (bool) $fee?->allowsInstallments());
        $this->check('Belum ada yang dibayar', $registration->paidAmount() <= 0);
        $this->check('Tidak dibebaskan voucher', ! $registration->isWaived());
        $this->check('Total lebih besar dari cicilan pertama', (float) $registration->amount > $registration->firstInstallmentAmount());

        $this->newLine();
        $this->pair('Cicilan pertama untuk invoice ini', 'IDR '.number_format($registration->firstInstallmentAmount(), 0, ',', '.'));
        $this->pair('Ditagih pada pembayaran berikutnya', 'IDR '.number_format($registration->amountDueNow(), 0, ',', '.'));

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
    }

    private function pair(string $label, string $value): void
    {
        $this->line('  '.str_pad($label, 42, '.').' '.$value);
    }

    private function check(string $label, bool $passed): void
    {
        $this->line('  '.($passed ? '<fg=green>OK  </>' : '<fg=red>GAGAL</>').' '.$label);
    }
}
