<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('git_deployment_id')
                ->nullable()
                ->constrained('git_deployments')
                ->nullOnDelete();

            $table->string('name');
            // Lista de etapas: [{'id','name','command','script','branch_override','env'}]
            $table->json('stages');
            $table->string('branch')->default('main');
            $table->string('last_run_status')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('enable_webhook')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['git_deployment_id', 'branch']);
        });

        Schema::create('deploy_pipeline_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('deploy_pipelines')->cascadeOnDelete();
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued');
            // Resultado por etapa: [{'stage','name','ok','log','duration_ms'}]
            $table->longText('output')->nullable();
            $table->string('triggered_by')->default('manual'); // manual | webhook
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['pipeline_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_pipeline_runs');
        Schema::dropIfExists('deploy_pipelines');
    }
};