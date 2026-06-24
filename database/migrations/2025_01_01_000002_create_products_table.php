<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ---- Data mentah dari Data Product.xlsx (Nama, Deskripsi, SKU, Harga) ----
            $table->string('sku')->nullable()->comment('SAP Part Number');
            $table->string('name');
            $table->longText('raw_description')->nullable()->comment('Deskripsi asli (HTML) dari Excel');
            $table->decimal('price', 15, 2)->nullable()->comment('Product Price (HET)');

            // ---- Atribut hasil refine AI (mengikuti "Konfirmasi Atribut untuk Simulasi") ----
            $table->string('part_category')->nullable()->comment('Tire, Lubricants, Battery, etc');
            $table->string('brand')->nullable()->comment('Aspira, Federal, GS Astra, etc');
            $table->string('type')->nullable()->comment('Bearing, Electrical, Filter, etc');
            $table->string('dimension')->nullable();
            $table->longText('description')->nullable()->comment('Deskripsi bersih hasil regenerate AI');
            $table->text('technical_specification')->nullable();
            $table->string('primary_image')->nullable();
            $table->json('additional_images')->nullable();

            // ---- Status pemrosesan ----
            // raw | refined | failed
            $table->string('refine_status', 20)->default('raw');
            // raw | refined | failed (khusus regenerate deskripsi)
            $table->string('description_status', 20)->default('raw');
            $table->text('ai_notes')->nullable();
            $table->timestamp('refined_at')->nullable();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->index('refine_status');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
