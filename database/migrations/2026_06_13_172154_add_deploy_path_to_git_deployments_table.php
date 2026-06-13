<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('git_deployments', function (Blueprint $table) {
            $table->string('deploy_path')->nullable()->after('domain_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('git_deployments', function (Blueprint $table) {
            $table->dropColumn('deploy_path');
        });
    }
};
