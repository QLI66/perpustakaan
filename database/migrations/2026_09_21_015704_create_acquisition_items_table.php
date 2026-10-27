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
        Schema::create('acquisition_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('acquisition_id')
                ->constrained('acquisitions')
                ->cascadeOnDelete();

            $table->foreignId('book_id')
                ->constrained('books')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['acquisition_id', 'book_id']);
            $table->index('book_id');
        });

        DB::statement('
            ALTER TABLE acquisition_items
            ADD CONSTRAINT check_acquisition_items_quantity_positive
            CHECK (quantity > 0)
        ');

        DB::statement('
            ALTER TABLE acquisition_items
            ADD CONSTRAINT check_acquisition_items_unit_price_non_negative
            CHECK (unit_price >= 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquisition_items');
    }
};
