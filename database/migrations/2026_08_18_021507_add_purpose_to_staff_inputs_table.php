<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->text('purpose')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};