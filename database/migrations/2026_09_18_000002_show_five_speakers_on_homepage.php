<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()->where('type', 'speakers')->each(function (PageSection $section): void {
            $settings = $section->settings ?? [];

            if ((int) ($settings['limit'] ?? 0) === 4) {
                $settings['limit'] = 5;
                $section->updateQuietly(['settings' => $settings]);
            }
        });
    }

    public function down(): void
    {
        PageSection::query()->where('type', 'speakers')->each(function (PageSection $section): void {
            $settings = $section->settings ?? [];

            if ((int) ($settings['limit'] ?? 0) === 5) {
                $settings['limit'] = 4;
                $section->updateQuietly(['settings' => $settings]);
            }
        });
    }
};
