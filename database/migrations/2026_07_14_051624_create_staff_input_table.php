<?php
// database/migrations/xxxx_xx_xx_create_staff_inputs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_inputs', function (Blueprint $table) {
            $table->id();
            
            // One-to-One relationship with form_inputs
            $table->foreignId('form_input_id')
                  ->constrained('form_input')
                  ->unique() // Ensures one-to-one
                  ->onDelete('cascade');
            
            // Foreign Keys
            $table->foreignId('fundcluster_id')
                  ->constrained('bankaccount_info')
                  ->onDelete('restrict');
            
            // Supporting document references (URLs)
            $table->string('ref_doc_1', 500)->nullable();
            $table->date('ref_date_1')->nullable();
            $table->string('ref_doc_2', 500)->nullable();
            $table->date('ref_date_2')->nullable();
            
            // UACS
            $table->foreignId('uacs_id')
                  ->constrained('uacs')
                  ->onDelete('restrict');
            
            // Status
            $table->enum('status', ['approved', 'pending', 'cancelled'])
                  ->default('pending');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_inputs');
    }
};