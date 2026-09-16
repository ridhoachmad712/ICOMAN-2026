<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cicilan pertama berbeda tergantung pilihan jurnal: 200.000 dari 350.000
     * untuk paper reguler, 350.000 dari 650.000 bila penerbitan SINTA 3 dipilih.
     * Satu angka tetap tidak bisa melayani keduanya.
     *
     * Seperti kolom sebelumnya, hanya cicilan PERTAMA yang disimpan — sisanya
     * selalu dihitung dari total, jadi keduanya tidak pernah berjumlah salah.
     */
    public function up(): void
    {
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->decimal('installment_first_amount_sinta3', 12, 2)
                ->nullable()
                ->after('installment_first_amount');
        });
    }

    public function down(): void
    {
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->dropColumn('installment_first_amount_sinta3');
        });
    }
};
