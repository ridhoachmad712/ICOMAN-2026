<?php

namespace App\Console\Commands;

use App\Models\Edition;
use App\Models\ImportantDate;
use App\Models\RegistrationFee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyConferenceCorrections extends Command
{
    protected $signature = 'icoman:apply-corrections {--usd-rate= : Approved IDR per USD billing rate}';
    protected $description = 'Apply the approved September 2026 prices and migrate public content without changing issued invoices.';

    public function handle(): int
    {
        $edition = Edition::where('name', 'ICOMAN 2026')->first();
        if (! $edition) {
            $this->error('ICOMAN 2026 edition not found.');

            return self::FAILURE;
        }
        $rate = $this->option('usd-rate');
        if ($rate !== null && (! is_numeric($rate) || (float) $rate <= 0)) {
            $this->error('Exchange rate must be a positive number.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($edition, $rate): void {
            // Repair the previously miscategorized international presenter fee in place.
            foreach ($edition->registrationFees()->get() as $fee) {
                if ($fee->audience === 'presenter' && str_contains(strtolower(json_encode($fee->getTranslations('category'))), 'international')) {
                    $fee->update(['registrant_category' => 'international']);
                }
            }
            foreach ([
                ['presenter', 'student_s1', 350000, 'IDR', 'Presenter — Undergraduate student', 'Presenter — Mahasiswa S1'],
                ['presenter', 'general', 400000, 'IDR', 'Presenter — General / Lecturer', 'Presenter — Umum / Dosen'],
                ['presenter', 'international', 25, 'USD', 'Presenter — International', 'Presenter — Internasional'],
                ['participant', 'student_s1', 35000, 'IDR', 'Seminar attendee — Undergraduate student', 'Peserta seminar — Mahasiswa S1'],
                ['participant', 'general', 50000, 'IDR', 'Seminar attendee — General', 'Peserta seminar — Umum'],
                ['participant', 'international', 5, 'USD', 'Seminar attendee — International', 'Peserta seminar — Internasional'],
            ] as $index => [$audience, $category, $amount, $currency, $en, $id]) {
                $values = ['price_regular' => $amount, 'currency' => $currency, 'category' => compact('en', 'id'), 'order' => $index];
                if ($currency === 'USD' && $rate !== null) {
                    $values['idr_exchange_rate'] = $rate;
                }
                RegistrationFee::updateOrCreate(['edition_id' => $edition->id, 'audience' => $audience, 'registrant_category' => $category], $values);
            }
            foreach (ImportantDate::where('edition_id', $edition->id)->whereNull('kind')->get() as $date) {
                $label = strtolower(json_encode($date->getTranslations('label')));
                $kind = match (true) {
                    str_contains($label, 'payment'), str_contains($label, 'pembayaran') => 'payment',
                    str_contains($label, 'acceptance'), str_contains($label, 'penerimaan') => 'acceptance',
                    str_contains($label, 'conference day'), str_contains($label, 'pelaksanaan') => 'conference',
                    str_contains($label, 'abstract'), str_contains($label, 'abstrak') => 'abstract',
                    default => null,
                };
                if ($kind) {
                    $date->update(['kind' => $kind]);
                }
                if ($kind === 'payment') {
                    $date->update(['label' => ['en' => 'Registration payment deadline', 'id' => 'Batas pembayaran registrasi']]);
                }
            }
            ImportantDate::firstOrCreate(['edition_id' => $edition->id, 'kind' => 'full_paper'], [
                'label' => ['en' => 'Full paper deadline', 'id' => 'Batas pengiriman full paper'], 'date' => null, 'order' => 99,
            ]);
            foreach (['speakers', 'sponsors', 'committees'] as $table) {
                // Only the original imported records are covered by this correction; new drafts stay private.
                foreach (DB::table($table)->where('edition_id', $edition->id)->where('created_at', '<', '2026-09-05 00:00:00')->get() as $record) {
                    $placeholder = preg_match('/tba|to be announced|bank contoh|media partner x|rector of|dean of|head of management/i', $record->name);
                    DB::table($table)->where('id', $record->id)->update(['is_published' => ! $placeholder]);
                }
            }
            $news = \App\Models\News::where('edition_id', $edition->id)->where('slug', 'submission-deadline-extended')->first();
            if ($news && str_contains(strip_tags($news->getTranslation('content', 'en')), 'Due to many requests, the deadline is extended.')) {
                $deadline = ImportantDate::where('edition_id', $edition->id)->where('kind', 'abstract')->first()?->date;
                if ($deadline) {
                    $date = $deadline->format('d M Y');
                    $news->update([
                        'title' => ['en' => 'Abstract submission deadline: '.$date, 'id' => 'Batas pengiriman abstrak: '.$date],
                        'excerpt' => ['en' => 'Submit your English abstract of 150–500 words by '.$date.'.', 'id' => 'Kirim abstrak bahasa Inggris sepanjang 150–500 kata paling lambat '.$date.'.'],
                        'content' => ['en' => '<p>The abstract submission deadline is '.$date.' at 23:59 WITA (UTC+8). Submit through your presenter account. Accepted abstracts receive an LOA before payment and full paper submission.</p>', 'id' => '<p>Batas pengiriman abstrak adalah '.$date.' pukul 23:59 WITA (UTC+8). Kirim melalui akun presenter. Abstrak yang diterima mendapatkan LOA sebelum pembayaran dan pengiriman full paper.</p>'],
                    ]);
                }
            }
        });
        $this->info('Six approved fee categories applied. Existing invoice totals preserved.');
        if (! $rate) {
            $this->warn('USD checkout requires the approved billing exchange rate in Registration Fees.');
        }

        return self::SUCCESS;
    }
}
