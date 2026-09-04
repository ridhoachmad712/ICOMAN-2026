<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Kredensial Midtrans dikelola dari admin (fallback ke .env bila kosong).
        $this->migrator->add('site.midtrans_merchant_id', null);
        $this->migrator->add('site.midtrans_client_key', null);
        $this->migrator->addEncrypted('site.midtrans_server_key', null);
        $this->migrator->add('site.midtrans_is_production', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.midtrans_merchant_id');
        $this->migrator->deleteIfExists('site.midtrans_client_key');
        $this->migrator->deleteIfExists('site.midtrans_server_key');
        $this->migrator->deleteIfExists('site.midtrans_is_production');
    }
};
