<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tawaran SINTA 3 kini mengikuti rekomendasi reviewer terus-menerus, bukan
     * sekali saat LOA terbit. Kolom ini menandai bahwa panitia sudah menetapkan
     * sendiri tawarannya, sehingga penilaian reviewer berikutnya tidak menimpa
     * keputusan itu.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('sinta3_offer_overridden_at')->nullable()->after('sinta3_offered');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('sinta3_offer_overridden_at');
        });
    }
};
