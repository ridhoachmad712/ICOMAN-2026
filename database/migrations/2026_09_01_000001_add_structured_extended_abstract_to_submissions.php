<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->json('extended_abstract_abstract')->nullable()->after('extended_abstract');
            $table->json('extended_abstract_introduction')->nullable()->after('extended_abstract_abstract');
            $table->json('extended_abstract_method')->nullable()->after('extended_abstract_introduction');
            $table->json('extended_abstract_results_discussion')->nullable()->after('extended_abstract_method');
            $table->json('extended_abstract_conclusion')->nullable()->after('extended_abstract_results_discussion');
            $table->timestamp('extended_abstract_draft_saved_at')->nullable()->after('extended_abstract_conclusion');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn([
                'extended_abstract_abstract',
                'extended_abstract_introduction',
                'extended_abstract_method',
                'extended_abstract_results_discussion',
                'extended_abstract_conclusion',
                'extended_abstract_draft_saved_at',
            ]);
        });
    }
};
