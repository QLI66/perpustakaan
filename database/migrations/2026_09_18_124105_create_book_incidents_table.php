<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_item_id')->unique()->constrained('loan_items')->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('OPEN');
            $table->string('resolution_type', 30)->nullable();
            $table->foreignId('replacement_book_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->decimal('amount_due', 14, 2)->nullable();
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // ── PostgreSQL CHECK constraints ────────────────────────────────────
        DB::statement('ALTER TABLE book_incidents ADD CONSTRAINT chk_bi_amount_due CHECK (amount_due IS NULL OR amount_due >= 0)');
        DB::statement('ALTER TABLE book_incidents ADD CONSTRAINT chk_bi_amount_paid CHECK (amount_paid IS NULL OR amount_paid >= 0)');
        DB::statement('ALTER TABLE book_incidents ADD CONSTRAINT chk_bi_amount_paid_le_due CHECK (amount_due IS NULL OR amount_paid IS NULL OR amount_paid <= amount_due)');

        // When RESOLVED: resolution_type and resolved_at must be present
        DB::statement("ALTER TABLE book_incidents ADD CONSTRAINT chk_bi_resolved_fields CHECK (
            status != 'RESOLVED' OR (resolution_type IS NOT NULL AND resolved_at IS NOT NULL)
        )");

        // When OPEN: resolution_type and resolved_at must be NULL
        DB::statement("ALTER TABLE book_incidents ADD CONSTRAINT chk_bi_open_fields CHECK (
            status != 'OPEN' OR (resolution_type IS NULL AND resolved_at IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('book_incidents');
    }
};
