<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_chunks', function (Blueprint $table) {
            $table->id();
            // Katalog (import batch bertipe 'catalog') sumber chunk ini.
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            // Vektor embedding disimpan sebagai JSON (array float). MySQL/MariaDB 10.x
            // belum punya tipe VECTOR native; cosine similarity dihitung di PHP.
            $table->longText('embedding')->nullable();
            $table->unsignedSmallInteger('dimensions')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->timestamps();

            $table->index(['import_batch_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_chunks');
    }
};
