<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_input', function (Blueprint $table) {
            $table->id();
            
            // One-to-One with bankaccount_info
            $table->foreignId('fundcluster_id')->constrained('bankaccount_info')->onDelete('restrict');
            
            // Reference documents (URLs)
            $table->string('ref_doc_1')->nullable();
            $table->date('ref_date_1')->nullable();
            $table->string('ref_doc_2')->nullable();
            $table->date('ref_date_2')->nullable();
            
            // One-to-One with UACS
            $table->foreignId('uacs_id')->constrained('uacs')->onDelete('restrict');
            
            // Foreign key to form_input if needed
            $table->foreignId('form_input_id')->constrained('form_input')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_input');
    }
};