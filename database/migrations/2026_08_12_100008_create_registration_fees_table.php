<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->json('category'); // (T) "Presenter (Domestic)"
            $table->decimal('price_early_bird', 12, 2)->nullable();
            $table->decimal('price_regular', 12, 2);
            $table->string('currency', 8)->default('IDR');
            $table->json('notes')->nullable(); // (T)
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_fees');
    }
};
