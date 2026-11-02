<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.registration_closed', false);
        $this->migrator->add('site.registration_closed_note', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.registration_closed');
        $this->migrator->deleteIfExists('site.registration_closed_note');
    }
};
