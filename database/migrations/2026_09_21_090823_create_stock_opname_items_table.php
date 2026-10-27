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
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_opname_id')
                ->constrained('stock_opnames')
                ->cascadeOnDelete();

            $table->foreignId('book_copy_id')
                ->constrained('book_copies')
                ->restrictOnDelete();

            $table->string('expected_status', 30);

            $table->string('actual_status', 30)->nullable();

            $table->string('expected_condition', 30);

            $table->string('actual_condition', 30)->nullable();

            $table->string('result', 30)->default('NOT_CHECKED');

            $table->timestamp('checked_at')->nullable();

            $table->foreignId('checked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'stock_opname_id',
                'book_copy_id',
            ]);

            $table->index('book_copy_id');
            $table->index('result');
            $table->index('checked_at');
        });

        DB::statement("
            ALTER TABLE stock_opname_items
            ADD CONSTRAINT check_stock_opname_items_result
            CHECK (
                result IN (
                    'NOT_CHECKED',
                    'FOUND',
                    'MISSING',
                    'DIFFERENT'
                )
            )
        ");

        DB::statement("
            ALTER TABLE stock_opname_items
            ADD CONSTRAINT check_stock_opname_items_status_values
            CHECK (
                actual_status IS NULL
                OR actual_status IN (
                    'AVAILABLE',
                    'BORROWED',
                    'RESERVED',
                    'LOST',
                    'DAMAGED'
                )
            )
        ");

        DB::statement("
            ALTER TABLE stock_opname_items
            ADD CONSTRAINT check_stock_opname_items_condition_values
            CHECK (
                actual_condition IS NULL
                OR actual_condition IN (
                    'NEW',
                    'GOOD',
                    'FAIR',
                    'POOR'
                )
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
