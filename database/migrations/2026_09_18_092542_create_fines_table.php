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
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_item_id')->unique()->constrained('loan_items')->restrictOnDelete();
            $table->unsignedInteger('late_days');
            $table->decimal('rate_per_day', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('UNPAID');
            $table->timestamp('assessed_at');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Add PostgreSQL specific CHECK constraints
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_late_days CHECK (late_days >= 0)');
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_rate_per_day CHECK (rate_per_day >= 0)');
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_amount CHECK (amount >= 0)');
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_amount_paid CHECK (amount_paid >= 0)');
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_amount_paid_valid CHECK (amount_paid <= amount)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
