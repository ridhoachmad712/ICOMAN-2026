<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->enum('category', ['steering', 'organizing', 'scientific', 'reviewer']);
            $table->string('name');
            $table->json('role_title')->nullable(); // (T) mis. "Chairman", "Sekretaris"
            $table->string('affiliation')->nullable();
            // foto -> Spatie Media Library collection `photo` (bukan kolom)
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
