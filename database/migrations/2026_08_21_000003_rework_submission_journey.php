<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('submissions', 'keywords')) {
            Schema::table('submissions', function (Blueprint $table) {
                $table->json('keywords')->nullable()->after('abstract_id');
                $table->longText('extended_abstract')->nullable()->after('keywords');
                $table->timestamp('extended_abstract_submitted_at')->nullable()->after('extended_abstract');
                $table->string('status', 50)->default('abstract_submitted')->change();
            });
        }

        DB::table('submissions')->where('status', 'submitted')->update(['status' => 'abstract_submitted']);
        DB::table('submissions')->where('status', 'under_review')->update(['status' => 'abstract_under_review']);
        DB::table('submissions')->where('status', 'revision_required')->update(['status' => 'abstract_submitted']);

        // Status accepted lama berarti paper lolos review awal. Pada alur baru,
        // author tetap harus membayar dan mengirim extended abstract.
        DB::table('submissions')->where('status', 'accepted')->update(['status' => 'abstract_approved']);

        if (! Schema::hasColumn('review_assignments', 'phase')) {
            // MySQL dapat memakai unique index lama sebagai penopang foreign key
            // submission_id. Buat index mandiri sebelum unique index itu dilepas.
            if (! $this->hasIndex('review_assignments', 'review_assignments_submission_id_journey_index')) {
                Schema::table('review_assignments', function (Blueprint $table) {
                    $table->index('submission_id', 'review_assignments_submission_id_journey_index');
                });
            }

            Schema::table('review_assignments', function (Blueprint $table) {
                $table->dropUnique(['submission_id', 'reviewer_id']);
            });

            Schema::table('review_assignments', function (Blueprint $table) {
                $table->string('phase', 30)->default('abstract')->after('reviewer_id')->index();
                $table->unique(['submission_id', 'reviewer_id', 'phase']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropUnique(['submission_id', 'reviewer_id', 'phase']);
            $table->dropIndex(['phase']);
            $table->dropColumn('phase');
            $table->unique(['submission_id', 'reviewer_id']);
            $table->dropIndex('review_assignments_submission_id_journey_index');
        });

        DB::table('submissions')->where('status', 'abstract_submitted')->update(['status' => 'submitted']);
        DB::table('submissions')->where('status', 'abstract_under_review')->update(['status' => 'under_review']);
        DB::table('submissions')->where('status', 'abstract_approved')->update(['status' => 'accepted']);
        DB::table('submissions')->whereIn('status', ['extended_abstract_submitted', 'extended_abstract_under_review'])->update(['status' => 'accepted']);

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['keywords', 'extended_abstract', 'extended_abstract_submitted_at']);
            $table->string('status', 30)->default('submitted')->change();
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn (object $row) => $row->Key_name === $index);
        }

        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn (object $row) => $row->name === $index);
        }

        return false;
    }
};
