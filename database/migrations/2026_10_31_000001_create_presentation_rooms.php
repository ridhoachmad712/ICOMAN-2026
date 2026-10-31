<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Room presentasi paralel pada hari pelaksanaan.
 *
 * - presentation_rooms: dibuat admin_room, masing-masing dipandu SATU
 *   moderator (akun moderator_room).
 * - room_presentations: satu baris per paper yang tampil. Absensi, nilai
 *   moderator, dan peringkat pemenang disimpan di baris ini juga, karena
 *   tiap paper hanya dinilai oleh satu orang (moderator room-nya).
 *
 * Dua role baru ikut dibuat di sini supaya server cukup menjalankan migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // Tautan atau nama breakout room Zoom untuk room ini.
            $table->string('meeting_url')->nullable();
            $table->string('meeting_note')->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            // Presenter baru melihat room-nya di dashboard setelah dipublikasikan.
            $table->boolean('is_published')->default(false);
            // Diisi saat moderator mengirim penilaian; sesudahnya nilai terkunci.
            $table->timestamp('scores_submitted_at')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['edition_id', 'order']);
        });

        Schema::create('room_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_room_id')->constrained('presentation_rooms')->cascadeOnDelete();
            // Satu paper hanya tampil di satu room.
            $table->foreignId('submission_id')->unique()->constrained('submissions')->cascadeOnDelete();
            $table->integer('order')->default(0);

            $table->string('attendance', 20)->nullable(); // present | represented | absent
            $table->string('presenter_name')->nullable();
            $table->timestamp('attendance_marked_at')->nullable();
            $table->foreignId('attendance_marked_by')->nullable()->constrained('users')->nullOnDelete();

            // Nilai per kriteria (0–100) dan rata-ratanya.
            $table->json('paper_scores')->nullable();
            $table->json('presenter_scores')->nullable();
            $table->decimal('paper_score', 5, 2)->nullable();
            $table->decimal('presenter_score', 5, 2)->nullable();
            $table->text('moderator_notes')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();

            // Juara 1–3 yang ditetapkan admin_room; nilai hanya acuan.
            $table->unsignedTinyInteger('best_paper_rank')->nullable();
            $table->unsignedTinyInteger('best_presenter_rank')->nullable();
            $table->timestamps();

            $table->index(['presentation_room_id', 'order']);
        });

        foreach (['admin_room', 'moderator_room'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('room_presentations');
        Schema::dropIfExists('presentation_rooms');
        Role::query()->whereIn('name', ['admin_room', 'moderator_room'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
