<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_assignment_id')->constrained('review_assignments')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // skala 1-100 (keputusan panitia)
            $table->text('comments_for_author')->nullable();
            $table->text('comments_for_committee')->nullable(); // catatan internal, tidak dilihat author
            $table->enum('recommendation', ['accept', 'minor_revision', 'major_revision', 'reject'])->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
