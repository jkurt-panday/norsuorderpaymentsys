<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('uacs', function (Blueprint $table): void {
                // Check if unique index exists before dropping
                $uniqueExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.STATISTICS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'uacs' 
                     AND INDEX_NAME = 'uacs_object_code_unique' 
                     AND NON_UNIQUE = 0"
                );

                if ($uniqueExists[0]->count > 0) {
                    $table->dropUnique('uacs_object_code_unique');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->unique('object_code');
        });
    }
};
