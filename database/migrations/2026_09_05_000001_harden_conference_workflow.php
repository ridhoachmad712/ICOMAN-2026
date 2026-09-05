<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_fees', fn (Blueprint $table) => $table->decimal('idr_exchange_rate', 16, 4)->nullable());
        Schema::table('registrations', function (Blueprint $table) {
            $table->json('pricing_snapshot')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->text('checkout_url')->nullable();
            $table->json('notification_history')->nullable();
            $table->index('gateway_reference');
        });
        Schema::table('important_dates', function (Blueprint $table) {
            $table->string('kind')->nullable()->index();
            $table->dateTime('closes_at')->nullable();
        });
        foreach (['speakers', 'sponsors', 'committees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_published')->default(false));
        }
        Schema::table('authors', function (Blueprint $table) {
            $table->string('terms_version')->nullable();
            $table->string('terms_locale', 5)->nullable();
        });
        Schema::create('submission_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('snapshot');
            $table->timestamps();
        });
        // Existing totals are authoritative. Do not reconstruct an old add-on from today's settings.
        DB::table('registrations')->orderBy('id')->each(function ($registration) {
            $fee = DB::table('registration_fees')->find($registration->registration_fee_id);
            DB::table('registrations')->where('id', $registration->id)->update([
                'pricing_snapshot' => json_encode([
                    'base_amount' => $registration->amount, 'addon_amount' => 0,
                    'quoted_addon_amount' => 0, 'currency' => $fee?->currency ?? 'IDR',
                    'category' => ['en' => 'Archived registration total', 'id' => 'Total registrasi arsip'],
                    'journal_target' => 'regular', 'legacy' => true,
                ]),
            ]);
        });
        Schema::table('registration_fees', fn (Blueprint $table) => $table->dropColumn(['price_early_bird', 'early_bird_deadline']));
    }

    public function down(): void
    {
        Schema::table('registration_fees', fn (Blueprint $table) => $table->dropColumn('idr_exchange_rate'));
        Schema::table('registration_fees', function (Blueprint $table) {
            $table->decimal('price_early_bird', 12, 2)->nullable();
            $table->date('early_bird_deadline')->nullable();
        });
        Schema::dropIfExists('submission_versions');
        Schema::table('authors', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_locale']));
        foreach (['speakers', 'sponsors', 'committees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_published'));
        }
        Schema::table('important_dates', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'closes_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway_reference']);
            $table->dropColumn(['checkout_url', 'notification_history']);
        });
        Schema::table('registrations', fn (Blueprint $table) => $table->dropColumn('pricing_snapshot'));
    }
};
