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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('member_number', 30)->unique();
            $table->string('name', 150);
            $table->foreignId('member_type_id')->constrained('member_types')->restrictOnDelete();
            $table->string('identity_number', 50)->nullable()->unique();
            $table->foreignId('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete();
            $table->foreignId('major_id')->nullable()->constrained('majors')->nullOnDelete();
            $table->date('joined_at');
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
