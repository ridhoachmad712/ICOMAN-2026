<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.organizer_name', null);
        $this->migrator->add('site.organizer_logo', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.organizer_name');
        $this->migrator->deleteIfExists('site.organizer_logo');
    }
};
