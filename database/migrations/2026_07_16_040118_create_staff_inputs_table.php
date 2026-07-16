<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_input_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('fundcluster_id')->constrained('bankaccount_infos')->restrictOnDelete();
            $table->foreignId('ref_document_id')->nullable()->constrained('supporting_documents')->nullOnDelete();
            $table->date('ref_date');
            $table->foreignId('uacs_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->timestamps();
            
            $table->index('status');
            $table->index('ref_date');
            $table->index(['form_input_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_inputs');
    }
};