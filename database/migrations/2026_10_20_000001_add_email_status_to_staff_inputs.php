<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->string('email_status')->nullable()->after('emailed_at');
            $table->text('email_error')->nullable()->after('email_status');
            $table->timestamp('email_queued_at')->nullable()->after('email_error');
        });
    }

    public function down(): void
    {
        Schema::table('staff_inputs', function (Blueprint $table) {
            $table->dropColumn(['email_status', 'email_error', 'email_queued_at']);
        });
    }
};
