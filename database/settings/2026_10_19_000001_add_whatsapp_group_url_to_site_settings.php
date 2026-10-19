<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Tautan undangan grup WhatsApp peserta ICOMAN 2026. Parameter pelacak
        // dari tombol bagikan WhatsApp (?s=…&p=…) dibuang; undangan tetap sah.
        $this->migrator->add('site.whatsapp_group_url', 'https://chat.whatsapp.com/Jhc3ulqkUxDEm6u9pknk8k');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.whatsapp_group_url');
    }
};
