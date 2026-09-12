<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher co-host: satu kode per institusi mitra dengan kuota paper gratis.
 * Penukaran dicatat di tabel terpisah supaya riwayatnya bisa diaudit dan satu
 * slot bisa dilepas admin tanpa merusak hitungan kuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('code', 40)->unique();     // selalu disimpan huruf besar
            $table->string('host_name');              // nama institusi co-host
            $table->unsignedSmallInteger('quota')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            // Satu registrasi hanya boleh memakai satu voucher.
            $table->foreignId('registration_id')->unique()->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });

        Schema::table('registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('registrations', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->after('registration_fee_id')
                    ->constrained('vouchers')->nullOnDelete();
            }

            if (! Schema::hasColumn('registrations', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
            }

            // enum -> string supaya nilai 'waived' (lunas tanpa transaksi apa pun)
            // bisa ditambahkan tanpa migrasi enum yang berbeda tiap driver.
            $table->string('payment_method', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
