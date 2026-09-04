<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            // Waktu penulis mengirim naskah lengkap (full paper) setelah pembayaran.
            $table->timestamp('full_paper_submitted_at')->nullable()->after('loa_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('full_paper_submitted_at');
        });
    }
};
