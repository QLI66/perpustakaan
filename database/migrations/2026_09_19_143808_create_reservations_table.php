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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number', 40)->unique();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->foreignId('book_id')->constrained('books')->restrictOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->restrictOnDelete();

            $table->string('status', 20)->default('PENDING'); // PENDING, READY, FULFILLED, CANCELLED, EXPIRED

            $table->timestamp('reserved_at');
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Performance Indexes
            $table->index('status');
            $table->index('reserved_at');
            $table->index('expires_at');
            $table->index('book_copy_id');
        });

        // Concurrency protection: Member cannot have multiple active reservations for the same book
        DB::statement("
            CREATE UNIQUE INDEX reservations_member_book_active_unique 
            ON reservations (member_id, book_id) 
            WHERE status IN ('PENDING', 'READY');
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
