<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete(); // peserta (bisa non-pemakalah)
            $table->foreignId('registration_fee_id')->constrained('registration_fees')->restrictOnDelete();
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->enum('payment_method', ['manual', 'gateway']);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'pending_verification', 'paid', 'failed'])->default('pending');
            // proof_file -> Media Library collection `payment_proof` (khusus payment_method=manual)
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_payload')->nullable(); // raw response terakhir untuk audit
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
