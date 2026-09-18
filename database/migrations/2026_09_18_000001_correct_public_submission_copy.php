<?php

use App\Models\ImportantDate;
use App\Models\News;
use App\Models\RegistrationFee;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        News::query()->each(function (News $news): void {
            foreach (['excerpt', 'content'] as $field) {
                $translations = $news->getTranslations($field);

                foreach ($translations as $locale => $value) {
                    $translations[$locale] = str_replace(
                        ['150–500', '150-500', '150 to 500', '150 sampai 500'],
                        ['200–250', '200-250', '200 to 250', '200 sampai 250'],
                        $value,
                    );
                }

                $news->setTranslations($field, $translations);
            }

            if ($news->isDirty()) {
                $news->saveQuietly();
            }
        });

        ImportantDate::query()->where('kind', 'abstract')->each(function (ImportantDate $date): void {
            $labels = $date->getTranslations('label');

            foreach ($labels as $locale => $label) {
                $labels[$locale] = preg_replace('/\s*(?:&|and|dan)\s*Full Paper/i', '', $label);
            }

            $date->setTranslations('label', $labels)->saveQuietly();
        });

        $feeNotes = [
            'Includes presentation slot (Zoom Breakout Room), international certificate, policy brief, and review feedback.' => 'Mencakup slot presentasi di ruang Zoom, sertifikat internasional, policy brief, dan umpan balik reviewer.',
            'Includes virtual presentation slot, international certificate, and journal recommendation eligibility.' => 'Mencakup slot presentasi virtual, sertifikat internasional, dan kesempatan memperoleh rekomendasi jurnal.',
            'Access to Main Zoom Room (Keynote & Plenary Panel Sessions) and International E-Certificate.' => 'Akses ke ruang Zoom utama untuk sesi keynote dan panel pleno, serta sertifikat elektronik internasional.',
        ];

        RegistrationFee::query()->each(function (RegistrationFee $fee) use ($feeNotes): void {
            $notes = $fee->getTranslations('notes');
            $english = $notes['en'] ?? null;

            if (blank($notes['id'] ?? null) && isset($feeNotes[$english])) {
                $notes['id'] = $feeNotes[$english];
                $fee->setTranslations('notes', $notes)->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Editorial corrections should not restore conflicting public guidance.
    }
};
