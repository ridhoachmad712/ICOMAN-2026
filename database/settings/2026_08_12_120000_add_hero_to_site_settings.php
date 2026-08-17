<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.event_location', null);
        $this->migrator->add('site.event_mode', null);
        $this->migrator->add('site.hero_image', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.event_location');
        $this->migrator->deleteIfExists('site.event_mode');
        $this->migrator->deleteIfExists('site.hero_image');
    }
};
