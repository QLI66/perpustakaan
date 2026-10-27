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
        Schema::create('acquisitions', function (Blueprint $table) {
            $table->id();

            $table->string('acquisition_number', 50)->unique();
            $table->date('acquisition_date');
            $table->string('source_type', 30);
            $table->string('source_name', 255)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('acquisition_date');
            $table->index('source_type');
        });

        DB::statement("
            ALTER TABLE acquisitions
            ADD CONSTRAINT check_acquisitions_source_type
            CHECK (source_type IN ('PURCHASE', 'DONATION', 'GRANT', 'GIFT', 'OTHER'))
        ");

        DB::statement('
            ALTER TABLE acquisitions
            ADD CONSTRAINT check_acquisitions_total_amount_non_negative
            CHECK (total_amount >= 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquisitions');
    }
};
