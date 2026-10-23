<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Template sertifikat terpisah untuk presenter dan peserta seminar, plus mode
 * template custom yang hanya mencetak data dinamis di posisi yang diatur.
 *
 * Latar tunggal yang lama dipindah menjadi latar presenter; latar peserta yang
 * kosong memakai latar presenter, jadi tampilan sertifikat lama tidak berubah.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->rename('site.certificate_background', 'site.certificate_background_presentation');
        $this->migrator->add('site.certificate_background_participation', null);
        $this->migrator->add('site.certificate_custom_layout', false);
        $this->migrator->add('site.certificate_layout', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.certificate_layout');
        $this->migrator->deleteIfExists('site.certificate_custom_layout');
        $this->migrator->deleteIfExists('site.certificate_background_participation');
        $this->migrator->rename('site.certificate_background_presentation', 'site.certificate_background');
    }
};
