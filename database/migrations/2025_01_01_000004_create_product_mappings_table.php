<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // vehicle_id boleh null bila AI hanya menemukan teks kendaraan tanpa match ke master vehicle
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Snapshot atribut kendaraan (mengikuti "Konfirmasi Atribut untuk Simulasi")
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('year')->nullable();
            $table->string('transmission')->nullable();

            // Asal data mapping: excel | ai_catalog | manual
            $table->string('source', 20)->default('ai_catalog');
            // Tingkat keyakinan AI 0-100
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->index('source');
            $table->index(['product_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mappings');
    }
};
