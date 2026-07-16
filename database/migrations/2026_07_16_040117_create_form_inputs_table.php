<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->string('email', 255);
            $table->string('contact_num', 20);
            $table->string('firstname_or_office', 100);
            $table->string('middlename_or_project', 100)->nullable();
            $table->string('lastname_or_agency', 100);
            $table->string('office_or_college', 100);
            $table->string('position_or_designation', 100);
            $table->text('address');
            $table->decimal('amount', 12, 2);
            $table->enum('request_type', ['New Request', 'Re-issue Request']);
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_detail_option_id')->constrained('payment_detail_options')->restrictOnDelete();
            $table->timestamps();
            
            $table->index('reference_number');
            $table->index('email');
            $table->index('contact_num');
            $table->index(['membership_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_inputs');
    }
};