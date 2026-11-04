<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.participant_registration_closed', false);
        $this->migrator->add('site.participant_payment_closed', false);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.participant_registration_closed');
        $this->migrator->deleteIfExists('site.participant_payment_closed');
    }
};
