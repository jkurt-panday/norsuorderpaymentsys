<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_input', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('contact_num', 20);
            $table->string('firstname_or_office');
            $table->string('middlename_or_project')->nullable();
            $table->string('lastname_or_agency');
            $table->string('office_or_college');
            $table->string('position_or_designation');
            $table->text('address');
            $table->decimal('amount', 10, 2);
            $table->enum('request_type', ['New Request', 'Re-issue Request']);

            // One-to-One Relationships
            $table->foreignId('membership_id')->constrained('membership')->onDelete('restrict');
            $table->foreignId('payment_details_id')->constrained('payment_details_options')->onDelete('restrict');

            // Supporting documents - will store as JSON array
            $table->json('supporting_documents')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_input');
    }
};
