<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Existing co-host codes retain their original behaviour.
            $table->string('type', 20)->default('cohost')->after('host_name');
            $table->string('audience', 20)->default('presenter')->after('type');
            $table->string('discount_kind', 20)->nullable()->after('audience');
            $table->decimal('discount_value', 12, 2)->nullable()->after('discount_kind');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['type', 'audience', 'discount_kind', 'discount_value']);
        });
    }
};
