<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa yang mencatat pembayaran ini dengan tangan.
 *
 * Pembayaran lewat gateway mengabarkan dirinya sendiri, jadi tidak ada orang
 * di baliknya. Tapi "Tandai Lunas" dan "Tolak" adalah keputusan seseorang
 * bahwa uangnya sudah diterima atau tidak — dan sampai sekarang keputusan itu
 * tidak meninggalkan nama sama sekali.
 *
 * Selama hanya superadmin yang bisa menekannya, itu masih bisa ditelusuri
 * lewat siapa yang punya akses. Begitu ada peran kedua yang boleh mencatat,
 * kolom ini yang menjadi pertanggungjawabannya.
 *
 * `nullOnDelete`: akun panitia yang dihapus tidak boleh menghapus catatan
 * pembayarannya. Yang hilang cukup namanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};
