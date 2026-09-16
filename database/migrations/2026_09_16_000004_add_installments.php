<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cicilan dua tahap untuk presenter mahasiswa.
     *
     * Hanya nominal cicilan PERTAMA yang disimpan; sisanya selalu dihitung dari
     * total tagihan, supaya dua angka itu tidak pernah bisa berjumlah salah.
     *
     * Status registrasi sengaja tidak ditambah nilai baru: sudah dibayar
     * sebagian tetap `pending` sampai lunas, sehingga seluruh gerbang yang
     * bergantung pada `paid` — termasuk unggah full paper — tetap berlaku apa
     * adanya.
     */
    public function up(): void
    {
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->decimal('installment_first_amount', 12, 2)->nullable()->after('price_regular');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('installment_plan')->default(false)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->dropColumn('installment_first_amount');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('installment_plan');
        });
    }
};
