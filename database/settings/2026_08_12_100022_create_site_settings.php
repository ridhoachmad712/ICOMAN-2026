<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.conference_name', 'ICOMAN 2026');
        $this->migrator->add('site.logo', null);
        $this->migrator->add('site.favicon', null);

        $this->migrator->add('site.primary_color', '#1d4ed8');
        $this->migrator->add('site.secondary_color', '#0f172a');

        $this->migrator->add('site.contact_email', null);
        $this->migrator->add('site.contact_whatsapp', null);
        $this->migrator->add('site.contact_address', null);

        $this->migrator->add('site.social_instagram', null);
        $this->migrator->add('site.social_twitter', null);
        $this->migrator->add('site.social_youtube', null);

        $this->migrator->add('site.google_maps_embed_url', null);

        $this->migrator->add('site.default_locale', 'en');
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.conference_name');
        $this->migrator->deleteIfExists('site.logo');
        $this->migrator->deleteIfExists('site.favicon');
        $this->migrator->deleteIfExists('site.primary_color');
        $this->migrator->deleteIfExists('site.secondary_color');
        $this->migrator->deleteIfExists('site.contact_email');
        $this->migrator->deleteIfExists('site.contact_whatsapp');
        $this->migrator->deleteIfExists('site.contact_address');
        $this->migrator->deleteIfExists('site.social_instagram');
        $this->migrator->deleteIfExists('site.social_twitter');
        $this->migrator->deleteIfExists('site.social_youtube');
        $this->migrator->deleteIfExists('site.google_maps_embed_url');
        $this->migrator->deleteIfExists('site.default_locale');
    }
};
