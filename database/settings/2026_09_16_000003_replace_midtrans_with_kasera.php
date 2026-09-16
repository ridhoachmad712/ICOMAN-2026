<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * Kasera Pay butuh dua rahasia yang terpisah: API key untuk memanggil API,
     * dan signing secret milik endpoint webhook untuk memverifikasi kiriman.
     * Midtrans memakai satu server key untuk kedua hal itu.
     */
    public function up(): void
    {
        $this->migrator->addEncrypted('site.kasera_api_key', null);
        $this->migrator->addEncrypted('site.kasera_webhook_secret', null);

        $this->migrator->deleteIfExists('site.midtrans_merchant_id');
        $this->migrator->deleteIfExists('site.midtrans_client_key');
        $this->migrator->deleteIfExists('site.midtrans_server_key');
        $this->migrator->deleteIfExists('site.midtrans_is_production');
    }

    public function down(): void
    {
        $this->migrator->add('site.midtrans_merchant_id', null);
        $this->migrator->add('site.midtrans_client_key', null);
        $this->migrator->addEncrypted('site.midtrans_server_key', null);
        $this->migrator->add('site.midtrans_is_production', false);

        $this->migrator->deleteIfExists('site.kasera_api_key');
        $this->migrator->deleteIfExists('site.kasera_webhook_secret');
    }
};
