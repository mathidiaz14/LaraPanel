<?php

namespace App\Console\Commands;

use App\Models\GitDeployment;
use App\Services\GitDeployDispatcher;
use App\Services\GitService;
use App\Services\PipelineService;
use Illuminate\Console\Command;

class DeployGitRepositoryCommand extends Command
{
    protected $signature = 'git:deploy
                            {deployment : ID de la configuración de despliegue}
                            {--trigger=manual : Origen del disparo (manual|webhook)}
                            {--commit= : Hash del commit que originó el despliegue}';

    protected $description = 'Ejecuta el despliegue de un repositorio Git configurado';

    public function handle(GitService $gitService): int
    {
        $deployment = GitDeployment::find($this->argument('deployment'));

        if (! $deployment) {
            $this->error("Deployment [{$this->argument('deployment')}] no encontrado.");

            return self::FAILURE;
        }

        $deploymentId = (int) $deployment->id;

        try {
            $log = $gitService->deploy(
                $deployment,
                (string) $this->option('trigger'),
                $this->option('commit'),
            );
        } finally {
            GitDeployDispatcher::forget($deploymentId);
        }

        if ($log->status === 'success' && (string) $this->option('trigger') === 'webhook') {
            try {
                $run = app(PipelineService::class)->handleWebhook(
                    $deployment,
                    trim((string) $deployment->branch)
                );

                if ($run) {
                    $this->info("Pipeline #{$run->pipeline_id} ejecutado con estado: {$run->status}");
                }
            } catch (\Throwable $e) {
                $this->warn('Pipeline webhook no ejecutado: ' . $e->getMessage());
            }
        }

        $this->info("Deploy finalizado con estado: {$log->status}");

        return $log->status === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
