<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Polymorphic pointer to the record that was changed. Unlike
            // target_id (which is a hard FK to users, used for the affected
            // account on provisioning), these are NOT foreign keys so any
            // model type can be logged here.
            $table->string('subject_type')->nullable()->after('target_id')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type')->index();

            // Snapshot of the acting user so the feed stays readable even if
            // the user is later deleted. actor_id remains the live FK.
            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('actor_role')->nullable()->after('actor_name');

            // Human-readable summary of what happened (searchable).
            $table->text('description')->nullable()->after('actor_role');

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['subject_type']);
            $table->dropIndex(['subject_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);

            $table->dropColumn([
                'subject_type',
                'subject_id',
                'actor_name',
                'actor_role',
                'description',
            ]);
        });
    }
};
