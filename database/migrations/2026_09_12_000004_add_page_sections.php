<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blok penyusun halaman. Satu baris = satu section pada satu halaman, dengan
 * urutan yang bisa diatur admin. Selama sebuah halaman belum punya baris di
 * sini, halaman itu tetap memakai susunan bawaannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->nullable()->constrained('editions')->cascadeOnDelete();
            // Halaman tujuan: 'home', atau 'page:{slug}' untuk halaman CMS.
            $table->string('target', 60)->index();
            $table->string('type', 30);
            $table->json('eyebrow')->nullable();     // (T)
            $table->json('heading')->nullable();     // (T)
            $table->json('subheading')->nullable();  // (T)
            $table->json('content')->nullable();     // (T) rich text
            // Pengaturan khusus tiap jenis blok: jumlah kartu, tautan tombol, dsb.
            $table->json('settings')->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            // Gambar blok -> Media Library, collection `section`.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
