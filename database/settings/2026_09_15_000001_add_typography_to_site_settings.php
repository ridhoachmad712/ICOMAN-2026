<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Tipografi menyeluruh: berlaku untuk seluruh halaman publik sekaligus.
        $this->migrator->add('site.font_heading', 'Space Grotesk');
        $this->migrator->add('site.font_body', 'Instrument Sans');
        $this->migrator->add('site.base_font_size', 16);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.font_heading');
        $this->migrator->deleteIfExists('site.font_body');
        $this->migrator->deleteIfExists('site.base_font_size');
    }
};
