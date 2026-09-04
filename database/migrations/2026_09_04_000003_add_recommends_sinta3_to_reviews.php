<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            // Reviewer merekomendasikan naskah untuk jalur publikasi lanjutan (SINTA 3).
            $table->boolean('recommends_sinta3')->default(false)->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('recommends_sinta3');
        });
    }
};
