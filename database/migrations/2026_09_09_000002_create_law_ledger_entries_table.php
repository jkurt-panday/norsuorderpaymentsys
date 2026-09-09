<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('law_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->decimal('units', 8, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('entry_type', 30)->index();
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date')->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('particulars');
            $table->text('remarks')->nullable();
            $table->string('status', 30)->index();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'academic_term_id']);
            $table->index(['student_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('law_ledger_entries');
    }
};
