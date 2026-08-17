<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.bank_name', null);
        $this->migrator->add('site.bank_account_number', null);
        $this->migrator->add('site.bank_account_holder', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.bank_name');
        $this->migrator->deleteIfExists('site.bank_account_number');
        $this->migrator->deleteIfExists('site.bank_account_holder');
    }
};
