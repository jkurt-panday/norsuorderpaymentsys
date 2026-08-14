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
        Schema::create('assessment_forms', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('contact_num', 11);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->foreignId('courses')->constrained()->cascadeOnDelete();
            $table->text('address');
            $table->string('enrolled_under', 100);
            $table->string('sy_last_attended', 100);
            $table->string('semester', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_forms');
    }
};
