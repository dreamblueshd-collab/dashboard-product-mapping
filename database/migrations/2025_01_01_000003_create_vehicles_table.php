<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // ---- Data mentah dari Data Vehicle.xlsx (16 kolom berkode) ----
            $table->string('group_code')->nullable();
            $table->string('group_description')->nullable();
            $table->string('type_code')->nullable();
            $table->string('type_description')->nullable();
            $table->string('brand_code')->nullable();
            $table->string('brand_description')->nullable();
            $table->string('sub_brand_code')->nullable();
            $table->string('sub_brand_description')->nullable();
            $table->string('variant_code')->nullable();
            $table->string('model_description')->nullable();
            $table->string('transmission_code')->nullable();
            $table->string('transmission_description')->nullable();
            $table->string('machine_type_code')->nullable();
            $table->string('machine_type_description')->nullable();
            $table->string('machine_volume_code')->nullable();
            $table->string('machine_volume_description')->nullable();

            // ---- Field tambahan yang dilengkapi AI ----
            $table->string('common_name')->nullable()->comment('Nama umum kendaraan (hasil AI)');
            $table->string('release_year')->nullable()->comment('Tahun keluaran (hasil AI)');

            // ---- Status pemrosesan ----
            $table->string('refine_status', 20)->default('raw'); // raw | refined | failed
            $table->text('ai_notes')->nullable();
            $table->timestamp('refined_at')->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->index('refine_status');
            $table->index(['brand_description', 'model_description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
