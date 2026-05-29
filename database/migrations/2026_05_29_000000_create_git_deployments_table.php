<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('git_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Since domains module might not have a table yet or might be named differently in this stub, 
            // we'll just link it to a domain name string to ensure it works standalone.
            $table->string('domain_name'); 
            $table->string('repository_url');
            $table->string('branch')->default('main');
            $table->text('deploy_script')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('webhook_id')->unique(); // Used for the public URL /api/webhooks/git/{webhook_id}
            $table->boolean('auto_deploy')->default(true);
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
            
            $table->index('webhook_id');
            $table->index('domain_name');
        });

        Schema::create('git_deployment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('git_deployment_id')->constrained()->cascadeOnDelete();
            $table->string('commit_hash')->nullable();
            $table->string('commit_message')->nullable();
            $table->enum('status', ['pending', 'running', 'success', 'failed'])->default('pending');
            $table->longText('output')->nullable();
            $table->string('triggered_by')->default('webhook'); // webhook | manual
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('git_deployment_logs');
        Schema::dropIfExists('git_deployments');
    }
};
