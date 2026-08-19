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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('firstname_or_office', 100)->nullable();
            $table->string('middlename_or_project', 100)->nullable();
            $table->string('lastname_or_agency', 100)->nullable();
            $table->string('contact_num', 50)->nullable();
            $table->string('office_or_college', 100)->nullable();
            $table->string('position_or_designation', 100)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
