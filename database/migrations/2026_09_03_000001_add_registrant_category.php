<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            if (! Schema::hasColumn('authors', 'registrant_category')) {
                // 'student_s1' (mahasiswa S1) | 'general' (dosen/umum, termasuk S2/S3)
                $table->string('registrant_category', 20)->nullable()->after('participation_type');
            }
        });

        Schema::table('registration_fees', function (Blueprint $table) {
            if (! Schema::hasColumn('registration_fees', 'registrant_category')) {
                $table->string('registrant_category', 20)->default('general')->after('audience');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn('registrant_category');
        });
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->dropColumn('registrant_category');
        });
    }
};
