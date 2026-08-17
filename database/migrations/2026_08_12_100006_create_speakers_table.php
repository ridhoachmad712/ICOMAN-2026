<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->enum('type', ['keynote', 'invited', 'plenary']);
            $table->string('name');
            $table->string('title_degree')->nullable(); // mis. "Prof. Dr."
            $table->string('affiliation')->nullable();
            $table->string('country')->nullable();
            $table->json('topic')->nullable(); // (T)
            $table->json('bio')->nullable(); // (T)
            // foto -> Spatie Media Library collection `photo`
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speakers');
    }
};
