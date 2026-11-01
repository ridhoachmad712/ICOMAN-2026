<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher yang juga memotong biaya tambahan Jurnal SINTA 3. Voucher lama
 * tetap hanya memotong biaya registrasi dasar, seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('covers_sinta3')->default(false)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('covers_sinta3');
        });
    }
};
