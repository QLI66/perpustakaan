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
        Schema::create('book_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete();
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete();
            $table->timestamps();
        });

        // A. Exact target: (book_id, class_level_id NOT NULL, major_id NOT NULL)
        DB::statement('CREATE UNIQUE INDEX book_targets_exact_idx ON book_targets (book_id, class_level_id, major_id) WHERE class_level_id IS NOT NULL AND major_id IS NOT NULL');

        // B. All majors within class: (book_id, class_level_id NOT NULL, major_id NULL)
        DB::statement('CREATE UNIQUE INDEX book_targets_class_only_idx ON book_targets (book_id, class_level_id) WHERE class_level_id IS NOT NULL AND major_id IS NULL');

        // C. All classes within major: (book_id, class_level_id NULL, major_id NOT NULL)
        DB::statement('CREATE UNIQUE INDEX book_targets_major_only_idx ON book_targets (book_id, major_id) WHERE class_level_id IS NULL AND major_id IS NOT NULL');

        // D. Global: (book_id, class_level_id NULL, major_id NULL)
        DB::statement('CREATE UNIQUE INDEX book_targets_global_idx ON book_targets (book_id) WHERE class_level_id IS NULL AND major_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_targets');
    }
};
