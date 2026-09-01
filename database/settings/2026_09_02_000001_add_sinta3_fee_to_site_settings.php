<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Biaya tambahan penerbitan ke jurnal SINTA 3 (dijumlahkan ke registrasi presenter).
        $this->migrator->add('site.sinta3_fee', 0);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('site.sinta3_fee');
    }
};
