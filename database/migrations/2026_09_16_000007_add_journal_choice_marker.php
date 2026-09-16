<?php

use App\Models\Submission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai bahwa author sudah menentukan opsi penerbitannya.
     *
     * `journal_target` selalu berisi 'regular' sejak awal, jadi tanpa penanda
     * ini tidak ada cara membedakan "memilih reguler" dari "belum memilih" —
     * dan halaman pembayaran tidak bisa menunggu pilihan itu sebelum
     * menampilkan tagihannya.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->timestamp('journal_target_chosen_at')->nullable()->after('journal_target');
        });

        // Paper yang tawarannya tidak pernah dibuka tidak punya pilihan untuk
        // dibuat; menganggapnya belum memilih akan menahan mereka di langkah
        // yang tidak akan pernah muncul.
        Submission::whereNull('journal_target_chosen_at')
            ->where('sinta3_offered', false)
            ->update(['journal_target_chosen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('journal_target_chosen_at');
        });
    }
};
