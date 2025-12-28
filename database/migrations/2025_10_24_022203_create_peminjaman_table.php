<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('peminjaman', function (Blueprint $table) {
            $table->id();
            $table->string('kode_peminjaman')->nullable()->index();
            $table->foreignId('radio_id')->constrained('radios')->cascadeOnDelete();
            $table->foreignId('peminjam_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('tgl_pinjam');
            $table->dateTime('tgl_jatuh_tempo')->index();
            $table->enum('status', ['pending', 'approved', 'dipinjam', 'dikembalikan', 'dibatalkan', 'terlambat'])->default('pending')->index();
            $table->string('keperluan')->nullable();
            $table->string('lokasi_penggunaan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // bantu query “radio sedang aktif dipinjam?”
            $table->index(['radio_id', 'status']);
            $table->index(['peminjam_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peminjaman');
    }
};
