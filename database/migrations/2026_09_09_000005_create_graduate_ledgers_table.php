<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduate_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->decimal('units', 8, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('entry_type', 30)->index('new_grad_ledger_entry_type_idx');
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date')->nullable()->index('new_grad_ledger_transaction_date_idx');
            $table->string('reference_number', 100)->nullable()->index('new_grad_ledger_reference_number_idx');
            $table->string('particulars');
            $table->text('remarks')->nullable();
            $table->string('status', 30)->index('new_grad_ledger_status_idx');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'academic_term_id'], 'new_grad_ledger_student_term_idx');
            $table->index(['student_id', 'transaction_date'], 'new_grad_ledger_student_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduate_ledgers');
    }
};
