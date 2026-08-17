<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete(); // submitter/corresponding utama
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('submission_number')->unique(); // auto-generate, mis. ICOMAN2026-0001
            $table->string('title');
            $table->text('abstract');
            $table->text('abstract_id')->nullable(); // versi Indonesia (opsional)
            // file -> Media Library collection `paper` (docx/pdf)
            // camera_ready_file -> Media Library collection `camera_ready`
            $table->enum('status', ['submitted', 'under_review', 'revision_required', 'accepted', 'rejected'])->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
