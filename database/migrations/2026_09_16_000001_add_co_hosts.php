<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan institusi co-host.
 *
 * Akunnya sendiri memakai tabel `authors` (participation_type = 'cohost'),
 * sehingga login, lupa password, invoice, dan pembayaran Midtrans dipakai ulang
 * apa adanya. Tabel ini hanya menambahkan yang memang khas co-host: identitas
 * institusi, penanggung jawab, dan status peninjauan panitia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('co_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->unique()->constrained('authors')->cascadeOnDelete();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();

            // Identitas institusi
            $table->string('institution_name');
            $table->string('institution_type', 40)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('website')->nullable();

            // Penanggung jawab; nama, email, dan telepon memakai data akun author.
            $table->string('pic_position')->nullable();

            // Peninjauan panitia
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Terbit saat disetujui; voucher baru aktif setelah invoicenya lunas.
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->constrained('sponsors')->nullOnDelete();

            $table->timestamps();
            // Logo institusi -> Media Library, collection `logo`.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_hosts');
    }
};
