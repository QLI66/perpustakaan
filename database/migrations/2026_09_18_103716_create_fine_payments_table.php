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
        Schema::create('fine_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fine_id')->constrained('fines')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at');
            $table->string('payment_method', 30)->default('CASH');
            $table->string('reference_number', 100)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Check constraint amount > 0
        DB::statement('ALTER TABLE fine_payments ADD CONSTRAINT chk_fine_payments_amount CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fine_payments');
    }
};
