<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('submissions')
            ->whereIn('status', ['abstract_submitted', 'abstract_under_review', 'abstract_approved'])
            ->update(['status' => 'extended_abstract_draft']);
    }

    public function down(): void
    {
        DB::table('submissions')
            ->where('status', 'extended_abstract_draft')
            ->update(['status' => 'abstract_submitted']);
    }
};
