<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepakaran reviewer: sub-tema apa saja yang ia kuasai.
 *
 * Dipakai saat admin registrasi menugaskan reviewer — pilihannya disaring ke
 * yang kepakarannya cocok dengan sub-tema yang dipilih author. Reviewer yang
 * belum diisi kepakarannya sengaja tetap dianggap siap untuk semua sub-tema,
 * supaya penyaringan menyala bertahap dan daftar tidak mendadak kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_user');
    }
};
