<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uacs', function (Blueprint $table) {
            $table->id();
            $table->string('classification', 255)->nullable();
            $table->string('sub_class', 255)->nullable();
            $table->string('group', 255)->nullable();
            $table->string('object_code', 255)->nullable();
            $table->string('sub_object_code', 255)->nullable();
            $table->string('uacs', 20)->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->index('uacs');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uacs');
    }
};
