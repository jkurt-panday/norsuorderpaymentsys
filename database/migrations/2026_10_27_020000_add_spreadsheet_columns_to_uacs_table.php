<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the spreadsheet-based UACS fields to databases where the original
     * UACS migration has already been executed.
     */
    public function up(): void
    {
        Schema::table('uacs', function (Blueprint $table) {
            if (! Schema::hasColumn('uacs', 'classification')) {
                $table->string('classification', 255)->nullable();
            }

            if (! Schema::hasColumn('uacs', 'sub_class')) {
                $table->string('sub_class', 255)->nullable();
            }

            if (! Schema::hasColumn('uacs', 'group')) {
                $table->string('group', 255)->nullable();
            }

            if (! Schema::hasColumn('uacs', 'sub_object_code')) {
                $table->string('sub_object_code', 255)->nullable();
            }

            if (! Schema::hasColumn('uacs', 'uacs')) {
                $table->string('uacs', 20)->nullable()->index();
            }

            if (! Schema::hasColumn('uacs', 'status')) {
                $table->string('status', 50)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('uacs', function (Blueprint $table) {
            $columns = [
                'classification',
                'sub_class',
                'group',
                'sub_object_code',
                'uacs',
                'status',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn('uacs', $column),
            ));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
