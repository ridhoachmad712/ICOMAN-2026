<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.certificate_background', null);
        $this->migrator->add('site.certificate_signatory_name', null);
        $this->migrator->add('site.certificate_signatory_title', null);
        $this->migrator->add('site.certificate_signature', null);
    }

    public function down(): void
    {
        foreach (['certificate_background', 'certificate_signatory_name', 'certificate_signatory_title', 'certificate_signature'] as $key) {
            $this->migrator->deleteIfExists('site.'.$key);
        }
    }
};
