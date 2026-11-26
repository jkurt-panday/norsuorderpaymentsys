<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supporting_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('supporting_documents', 'storage_provider')) {
                $table->string('storage_provider', 30)->default('google_drive')->after('file_url');
            }

            if (! Schema::hasColumn('supporting_documents', 'google_drive_file_id')) {
                $table->string('google_drive_file_id', 255)->nullable()->after('storage_provider')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supporting_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('supporting_documents', 'google_drive_file_id')) {
                $table->dropIndex(['google_drive_file_id']);
                $table->dropColumn('google_drive_file_id');
            }

            if (Schema::hasColumn('supporting_documents', 'storage_provider')) {
                $table->dropColumn('storage_provider');
            }
        });
    }
};
