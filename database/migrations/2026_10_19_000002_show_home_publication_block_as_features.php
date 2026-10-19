<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Blok "Publikasi & Luaran" di beranda kini memakai tata letak `features`
 * (pita biru, butir daftar sebagai kartu). Hanya blok yang tata letaknya
 * belum pernah dipilih panitia yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()
            ->where('target', 'home')
            ->where('type', 'page_content')
            ->get()
            ->filter(fn (PageSection $section) => $section->setting('page_slug') === 'publication'
                && in_array($section->setting('layout'), [null, 'plain'], true))
            ->each(function (PageSection $section): void {
                $section->settings = array_merge($section->settings ?? [], ['layout' => 'features']);
                $section->saveQuietly();
            });
    }

    public function down(): void
    {
        PageSection::query()
            ->where('target', 'home')
            ->where('type', 'page_content')
            ->get()
            ->filter(fn (PageSection $section) => $section->setting('page_slug') === 'publication'
                && $section->setting('layout') === 'features')
            ->each(function (PageSection $section): void {
                $section->settings = array_merge($section->settings ?? [], ['layout' => 'plain']);
                $section->saveQuietly();
            });
    }
};
