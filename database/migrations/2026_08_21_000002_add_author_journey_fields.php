<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('participation_type', 30)->nullable()->after('phone')->index();
        });

        Schema::table('registration_fees', function (Blueprint $table) {
            $table->string('audience', 30)->nullable()->after('category')->index();
        });

        DB::table('registration_fees')
            ->select(['id', 'category'])
            ->orderBy('id')
            ->each(function (object $fee): void {
                $category = json_decode((string) $fee->category, true);
                $label = is_array($category)
                    ? implode(' ', array_filter($category, 'is_string'))
                    : (string) $fee->category;
                $label = mb_strtolower($label);

                $audience = str_contains($label, 'non-presenter') || str_contains($label, 'non-pemakalah')
                    ? 'participant'
                    : 'presenter';

                DB::table('registration_fees')->where('id', $fee->id)->update(['audience' => $audience]);
            });

        DB::table('authors')->select('id')->orderBy('id')->each(function (object $author): void {
            $hasSubmission = DB::table('submissions')->where('author_id', $author->id)->exists();
            $audience = DB::table('registrations')
                ->join('registration_fees', 'registration_fees.id', '=', 'registrations.registration_fee_id')
                ->where('registrations.author_id', $author->id)
                ->value('registration_fees.audience');

            $type = $hasSubmission ? 'presenter' : $audience;

            if ($type) {
                DB::table('authors')->where('id', $author->id)->update(['participation_type' => $type]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->dropIndex(['participation_type']);
            $table->dropColumn('participation_type');
        });
    }
};
