<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua pintu masuk kustomisasi website dari admin:
 *
 * - `site_texts` menyimpan SUNTINGAN saja atas label bawaan di lang/. Berkas
 *   bahasa tetap jadi cadangan, sehingga menghapus satu baris di sini
 *   mengembalikan teks aslinya, bukan mengosongkan halaman.
 * - `menu_items` menggantikan susunan menu yang selama ini tertulis tetap di
 *   navbar, supaya halaman buatan admin bisa ikut tampil di navigasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_texts', function (Blueprint $table) {
            $table->id();
            // Kunci penuh seperti di lang/, mis. "site.read_more".
            $table->string('key')->unique();
            $table->json('value'); // (T) per-locale
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->json('label'); // (T)
            // 'route' = halaman bawaan, 'page' = halaman CMS, 'url' = tautan bebas
            $table->string('type', 10)->default('route');
            $table->string('route_name')->nullable();
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            $table->string('url')->nullable();
            $table->boolean('opens_in_new_tab')->default(false);
            $table->boolean('is_published')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('site_texts');
    }
};
