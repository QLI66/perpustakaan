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
        Schema::table('book_copies', function (Blueprint $table) {
            $table->foreignId('acquisition_item_id')
                ->nullable()
                ->after('acquisition_price')
                ->constrained('acquisition_items')
                ->restrictOnDelete();

            $table->index('acquisition_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropForeign(['acquisition_item_id']);
            $table->dropIndex(['acquisition_item_id']);
            $table->dropColumn('acquisition_item_id');
        });
    }
};
