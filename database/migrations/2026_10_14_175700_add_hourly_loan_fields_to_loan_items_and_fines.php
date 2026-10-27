<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_items', function (Blueprint $table) {
            $table->string('duration_unit', 8)->default('day');
            $table->unsignedSmallInteger('duration_value')->nullable();
        });

        DB::statement(
            "ALTER TABLE loan_items ADD CONSTRAINT chk_loan_items_duration_unit CHECK (duration_unit IN ('hour', 'day'))"
        );
        DB::statement(
            "ALTER TABLE loan_items ADD CONSTRAINT chk_loan_items_duration_value CHECK (
                (duration_value IS NULL AND duration_unit = 'day')
                OR (duration_unit = 'hour' AND duration_value = 1)
                OR (duration_unit = 'day' AND duration_value BETWEEN 1 AND 3)
            )"
        );

        Schema::table('fines', function (Blueprint $table) {
            $table->string('late_unit', 8)->default('day');
            $table->unsignedInteger('late_units')->default(0);
            $table->decimal('rate_per_unit', 12, 2)->default(0);
        });

        DB::table('fines')->update([
            'late_units' => DB::raw('late_days'),
            'rate_per_unit' => DB::raw('rate_per_day'),
        ]);

        DB::statement(
            "ALTER TABLE fines ADD CONSTRAINT chk_fines_late_unit CHECK (late_unit IN ('hour', 'day'))"
        );
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_late_units CHECK (late_units >= 0)');
        DB::statement('ALTER TABLE fines ADD CONSTRAINT chk_fines_rate_per_unit CHECK (rate_per_unit >= 0)');
    }

    public function down(): void
    {
        throw new LogicException(
            'Hourly loan metadata is retained to prevent deleting loan or fine history during rollback.'
        );
    }
};
