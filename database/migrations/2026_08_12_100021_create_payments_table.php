<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log setiap percobaan/transaksi pembayaran (audit trail, termasuk yang gagal).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->enum('method', ['manual', 'gateway']);
            $table->string('gateway_name')->nullable(); // "midtrans"/"xendit"
            $table->string('gateway_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['initiated', 'success', 'failed'])->default('initiated');
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
