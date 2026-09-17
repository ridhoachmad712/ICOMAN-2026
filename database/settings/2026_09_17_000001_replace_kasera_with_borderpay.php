<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /**
     * BorderPay memakai dua rahasia seperti Kasera, tapi yang kedua berbeda
     * sifatnya: bukan signing secret untuk memverifikasi tanda tangan,
     * melainkan token statis yang dicocokkan apa adanya. Namanya dibuat jujur
     * supaya tidak ada yang mengira kiriman webhooknya bertanda tangan.
     */
    public function up(): void
    {
        $this->migrator->addEncrypted('site.borderpay_api_key', null);
        $this->migrator->addEncrypted('site.borderpay_webhook_token', null);

        $this->migrator->deleteIfExists('site.kasera_api_key');
        $this->migrator->deleteIfExists('site.kasera_webhook_secret');
    }

    public function down(): void
    {
        $this->migrator->addEncrypted('site.kasera_api_key', null);
        $this->migrator->addEncrypted('site.kasera_webhook_secret', null);

        $this->migrator->deleteIfExists('site.borderpay_api_key');
        $this->migrator->deleteIfExists('site.borderpay_webhook_token');
    }
};
