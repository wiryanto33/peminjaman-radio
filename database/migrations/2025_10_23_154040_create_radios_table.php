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
        Schema::create('radios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori_radios')->cascadeOnDelete();
            $table->string('merk')->nullable();
            $table->string('model')->nullable();
            $table->string('image')->nullable();
            $table->string('serial_no')->nullable()->unique();
            $table->enum('status', ['tersedia', 'dipinjam', 'perbaikan'])->default('tersedia')->index();
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat'])->default('baik')->index();
            $table->text('deskripsi')->nullable();
            // $table->integer('jumlah')->default(1);
            $table->timestamps();
            $table->index(['kategori_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radios');
    }
};
