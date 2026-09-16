<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kasera Pay menerbitkan sendiri nomor transaksinya (`payreq_<uuid>`), dan
     * itulah yang dipakai untuk menanyakan status. Nomor kita sendiri tetap di
     * `gateway_reference` — dikirim sebagai external_id sekaligus
     * Idempotency-Key — karena harus ada sebelum request pertama dibuat.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_payment_id')->nullable()->after('gateway_reference')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway_payment_id');
        });
    }
};
