<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('submissions', 'loa_issued_at')) {
                $table->timestamp('loa_issued_at')->nullable()->after('extended_abstract_submitted_at');
            }
            if (! Schema::hasColumn('submissions', 'sinta3_offered')) {
                $table->boolean('sinta3_offered')->default(false)->after('loa_issued_at');
            }
            if (! Schema::hasColumn('submissions', 'journal_target')) {
                // 'regular' (jurnal biasa) | 'sinta3'
                $table->string('journal_target', 20)->default('regular')->after('sinta3_offered');
            }
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['loa_issued_at', 'sinta3_offered', 'journal_target']);
        });
    }
};
