<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supporting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_input_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255)->unique();
            $table->string('file_url', 500);
            $table->string('mime_type', 100);
            $table->string('file_extension', 10);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_at');
            $table->timestamps();
            
            $table->index('form_input_id');
            $table->index('uploaded_at');
            $table->index('stored_filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supporting_documents');
    }
};