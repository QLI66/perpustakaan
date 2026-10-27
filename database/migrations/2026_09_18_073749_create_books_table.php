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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->nullable();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('language', 10)->default('id');
            $table->foreignId('collection_type_id')
                ->constrained('collection_types')
                ->restrictOnDelete();   // Jangan hapus collection type yang masih dipakai buku
            $table->foreignId('publisher_id')
                ->nullable()
                ->constrained('publishers')
                ->nullOnDelete();       // Publisher dihapus → publisher_id menjadi NULL
            $table->string('cover', 255)->nullable();
            $table->timestamps();

            // Unique hanya saat ISBN diisi (partial unique index)
            $table->unique('isbn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
