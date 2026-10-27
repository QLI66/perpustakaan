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
        Schema::table('fine_payments', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->unique('idempotency_key', 'fine_payments_idempotency_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fine_payments', function (Blueprint $table) {
            $table->dropUnique('fine_payments_idempotency_key_unique');
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
};
