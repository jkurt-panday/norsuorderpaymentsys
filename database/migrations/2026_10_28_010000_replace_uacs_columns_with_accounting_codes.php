<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the legacy UACS spreadsheet columns with the accounting-code
     * structure used by the current UACS master list.
     */
    public function up(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->string('norsu_object_code', 12)->nullable();
            $table->string('norsu_sub_object_code', 2)->nullable();
            $table->string('uacs_object_code', 10)->nullable();
            $table->string('uacs_sub_object_code', 2)->nullable();
            $table->string('rca_code', 8)->nullable();
            $table->string('account_title', 500)->nullable();
        });

        Schema::table('uacs', function (Blueprint $table): void {
            foreach ([
                'classification',
                'sub_class',
                'group',
                'object_code',
                'sub_object_code',
                'uacs',
                'status',
            ] as $column) {
                if (Schema::hasColumn('uacs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('uacs', function (Blueprint $table): void {
            $table->string('classification', 255)->nullable();
            $table->string('sub_class', 255)->nullable();
            $table->string('group', 255)->nullable();
            $table->string('object_code', 255)->nullable();
            $table->string('sub_object_code', 255)->nullable();
            $table->string('uacs', 20)->nullable();
            $table->string('status', 50)->nullable();
        });

        Schema::table('uacs', function (Blueprint $table): void {
            foreach ([
                'norsu_object_code',
                'norsu_sub_object_code',
                'uacs_object_code',
                'uacs_sub_object_code',
                'rca_code',
                'account_title',
            ] as $column) {
                if (Schema::hasColumn('uacs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
