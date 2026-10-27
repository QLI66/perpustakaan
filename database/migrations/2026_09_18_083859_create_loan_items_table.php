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
        Schema::create('loan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->timestamp('borrowed_at');
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->unsignedSmallInteger('renewal_count')->default(0);
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('due_at');
        });

        // Add PostgreSQL specific Partial Unique Index
        DB::statement("CREATE UNIQUE INDEX loan_items_book_copy_active_unique ON loan_items (book_copy_id) WHERE status = 'ACTIVE'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_items');
    }
};
