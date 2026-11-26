<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('git_deployment_logs', function (Blueprint $table) {
            $table->string('requested_commit')->nullable()->after('commit_hash');
            $table->unsignedInteger('duration_ms')->nullable()->after('output');
            $table->timestamp('finished_at')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('git_deployment_logs', function (Blueprint $table) {
            $table->dropColumn(['requested_commit', 'duration_ms', 'finished_at']);
        });
    }
};
