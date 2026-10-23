<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.zoom_url', null);
        $this->migrator->add('site.zoom_meeting_id', null);
        $this->migrator->add('site.zoom_passcode', null);
        $this->migrator->add('site.zoom_note', null);
    }

    public function down(): void
    {
        foreach (['zoom_url', 'zoom_meeting_id', 'zoom_passcode', 'zoom_note'] as $key) {
            $this->migrator->deleteIfExists('site.'.$key);
        }
    }
};
