<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat yang sudah diterbitkan. Nama, afiliasi, dan judul paper disalin
 * saat terbit supaya perubahan profil sesudahnya tidak mengubah sertifikat
 * yang sudah beredar. Baris tetap ada meski akun atau paper dihapus, agar QR
 * yang sudah tercetak tetap bisa diverifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            // Akun yang mengunduh: presenter pengirim paper, atau peserta seminar.
            $table->foreignId('author_id')->nullable()->constrained('authors')->nullOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            // Penulis paper yang namanya tercantum (satu sertifikat per penulis).
            $table->foreignId('submission_author_id')->nullable()->constrained('submission_authors')->nullOnDelete();
            $table->string('type', 20); // presentation | participation
            $table->string('number')->unique();
            $table->string('recipient_name');
            $table->string('recipient_affiliation')->nullable();
            $table->text('paper_title')->nullable();
            // Kode acak di URL QR: bukan nomor urut, supaya tidak bisa ditebak.
            $table->string('verification_token', 64)->unique();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
