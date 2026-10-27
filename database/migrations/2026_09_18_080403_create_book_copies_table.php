<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('barcode', 100)->unique();
            $table->string('inventory_number', 100)->nullable()->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->date('acquisition_date')->nullable();
            $table->string('acquisition_source', 30)->default('PURCHASE');
            $table->decimal('acquisition_price', 14, 2)->nullable();
            $table->string('condition', 30)->default('GOOD');
            $table->string('status', 30)->default('AVAILABLE');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pastikan harga tidak pernah negatif
        DB::statement('ALTER TABLE book_copies ADD CONSTRAINT check_book_copies_acquisition_price_non_negative CHECK (acquisition_price >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
