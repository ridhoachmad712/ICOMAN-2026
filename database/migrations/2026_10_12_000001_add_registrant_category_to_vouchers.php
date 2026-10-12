<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Existing vouchers keep their original unrestricted eligibility.
            $table->string('registrant_category', 20)->default('all')->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('registrant_category');
        });
    }
};
