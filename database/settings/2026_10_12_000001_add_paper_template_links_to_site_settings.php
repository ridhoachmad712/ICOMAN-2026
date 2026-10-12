<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.paper_template_regular_url', null);
        $this->migrator->add('site.paper_template_sinta3_url', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.paper_template_regular_url');
        $this->migrator->deleteIfExists('site.paper_template_sinta3_url');
    }
};
