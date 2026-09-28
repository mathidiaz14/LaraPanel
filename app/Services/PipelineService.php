<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DeployPipeline;
use App\Models\DeployPipelineRun;
use App\Models\GitDeployment;
use App\Models\User;
use App\Shell\ShellExecutor;
use App\Shell\SudoExecutor;
use Illuminate\Database\Eloquent\Collection;

/**
 * PipelineService — CI/CD pipeline visual builder layered on the existing
 * Git deploy. Pipelines store an ordered list of stages (command + optional
 * bash script) that run inside the deployment's document root.
 *
 * Security: user scripts are NEVER interpolated into `['sh','-c', ...]`.
 * Each stage script is written to a generated server-side temp file under
 * storage/app/tmp, copied to /tmp owned by the sudo user via SudoExecutor
 * `cp`/`chown`, executed with `['bash', $tmpFile]` (bash is whitelisted in
 * config/larapanel.php) inside the document root, then deleted.
 */
class PipelineService
{
    protected const VALID_BRANCH = '/^[A-Za-z0-9._\/-]{1,200}$/';

    public function __construct(protected SudoExecutor $sudo) {}

    /**
     * Pipelines owned by the given user (defaults to authenticated user).
     */
    public function forUser(?User $user = null): Collection
    {
        $user ??= auth()->user();

        return ($user)
            ? DeployPipeline::with('gitDeployment')->where('user_id', $user->id)->latest()->get()
            : collect();
    }

    public function createForUser(array $data, ?User $user = null): DeployPipeline
    {
        $user ??= auth()->user();
        if (! $user) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        $data = $this->validatePayload($data);

        $pipeline = DeployPipeline::create(array_merge($data, ['user_id' => $user->id]));

        AuditLog::record('pipeline.create', $pipeline->name, [
            'pipeline_id' => $pipeline->id,
            'stages'      => count($pipeline->stages ?? []),
        ], userId: $user->id);

        return $pipeline;
    }

    public function updateForUser(DeployPipeline $pipeline, array $data, ?User $user = null): DeployPipeline
    {
        $this->authorize($pipeline, $user);

        $data = $this->validatePayload($data);
        $pipeline->update($data);

        AuditLog::record('pipeline.update', $pipeline->name, [
            'pipeline_id' => $pipeline->id,
            'stages'      => count($pipeline->stages ?? []),
        ], userId: $pipeline->user_id);

        return $pipeline;
    }

    public function delete(DeployPipeline $pipeline, ?User $user = null): void
    {
        $this->authorize($pipeline, $user);

        AuditLog::record('pipeline.delete', $pipeline->name, [
            'pipeline_id' => $pipeline->id,
        ], userId: $pipeline->user_id);

        $pipeline->delete();
    }

    /**
     * Execute every stage in order inside the deployment document root.
     * Stops at the first failing stage. Captures per-stage output into the
     * run row (JSON) so it can be rendered/polled later.
     */
    public function run(DeployPipeline $pipeline, string $trigger = 'manual', ?int $userId = null, ?callable $onOutput = null): DeployPipelineRun
    {
        $userId ??= $pipeline->user_id;

        /** @var DeployPipelineRun $run */
        $run = $pipeline->runs()->create([
            'status'       => 'queued',
            'output'       => [],
            'triggered_by' => $trigger,
        ]);

        $ok = false;

        try {
            $stages = $this->stagesFor($pipeline);
            if ($stages === []) {
                throw new \RuntimeException('El pipeline no tiene etapas configuradas.');
            }

            $run->update(['status' => 'running', 'started_at' => now()]);
            if ($onOutput) {
                $onOutput(">>> Iniciando pipeline «{$pipeline->name}» (".($trigger === 'webhook' ? 'webhook' : 'manual').")\n");
            }

            $executed = app()->isProduction()
                ? $this->executeStages($pipeline, $stages, $onOutput)
                : $this->simulateStages($pipeline, $stages, $trigger, $onOutput);

            $ok = ! in_array(false, array_map(fn ($s) => (bool) $s['ok'], $executed), true);
            $run->update([
                'status'      => $ok ? 'success' : 'failed',
                'output'      => $executed,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'output' => [[
                    'stage'       => 'error',
                    'name'        => 'Error de pipeline',
                    'ok'          => false,
                    'log'         => $e->getMessage(),
                    'duration_ms' => 0,
                ]],
                'finished_at' => now(),
            ]);
        }

        $pipeline->update([
            'last_run_status' => $run->status,
            'last_run_at'     => now(),
        ]);

        AuditLog::record('pipeline.run', $pipeline->name, [
            'pipeline_id' => $pipeline->id,
            'run_id'      => $run->id,
            'status'      => $run->status,
            'triggered_by'=> $trigger,
        ], severity: $run->status === 'failed' ? 'error' : 'info', userId: $userId);

        if ($onOutput) {
            $onOutput("\n>>> Pipeline finalizado con estado: {$run->status}.\n");
        }

        return $run;
    }

    /**
     * Streaming variant used by Livewire: forwards each output chunk through
     * the callback (which typically calls $this->stream() on the component).
     */
    public function runStreaming(DeployPipeline $pipeline, callable $onOutput, string $trigger = 'manual'): DeployPipelineRun
    {
        return $this->run($pipeline, $trigger, onOutput: $onOutput);
    }

    /**
     * Trigger pipelines wired to a Git deployment when a webhook push matches
     * the pipeline branch. Runs detached from the webhook response because it
     * is called from the `git:deploy` artisan command already spawned with
     * setsid/nohup by GitWebhookController.
     */
    public function handleWebhook(GitDeployment $deployment, string $branch): ?DeployPipelineRun
    {
        $pipelines = DeployPipeline::where('git_deployment_id', $deployment->id)
            ->where('enable_webhook', true)
            ->get();

        $run = null;

        foreach ($pipelines as $pipeline) {
            if (trim((string) $pipeline->branch) === trim($branch)) {
                $run = $this->run($pipeline, 'webhook', $pipeline->user_id);
            }
        }

        return $run;
    }

    public function documentRoot(DeployPipeline $pipeline): string
    {
        if ($pipeline->gitDeployment && trim((string) $pipeline->gitDeployment->deploy_path) !== '') {
            return $pipeline->gitDeployment->deploy_path;
        }

        $name = $pipeline->gitDeployment?->domain_name;
        if (is_string($name) && $name !== '') {
            return '/var/www/' . $name . '/public_html';
        }

        return rtrim((string) config('larapanel.paths.webroots', '/var/www'), '/');
    }

    // ───────────────────────────────────────────────────────────────────
    // Internals
    // ───────────────────────────────────────────────────────────────────

    protected function authorize(DeployPipeline $pipeline, ?User $user = null): void
    {
        $user ??= auth()->user();

        if ($user && ($user->id === $pipeline->user_id || $user->isAdmin())) {
            return;
        }

        throw new \RuntimeException('No tienes permiso para gestionar este pipeline.');
    }

    protected function stagesFor(DeployPipeline $pipeline): array
    {
        return $this->sanitizeStages(is_array($pipeline->stages) ? $pipeline->stages : []);
    }

    protected function validatePayload(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('El nombre del pipeline debe tener entre 2 y 120 caracteres.');
        }

        $branch = trim((string) ($data['branch'] ?? 'main'));
        if (! preg_match(self::VALID_BRANCH, $branch) || str_starts_with($branch, '-')) {
            throw new \InvalidArgumentException('La rama del pipeline no es válida.');
        }

        $gitDeploymentId = $data['git_deployment_id'] ?? null;
        if ($gitDeploymentId !== null && $gitDeploymentId !== '' && (int) $gitDeploymentId > 0) {
            $gitDeploymentId = (int) $gitDeploymentId;
            if (! GitDeployment::whereKey($gitDeploymentId)->exists()) {
                throw new \InvalidArgumentException('El despliegue Git seleccionado no existe.');
            }
        } else {
            $gitDeploymentId = null;
        }

        return [
            'name'              => $name,
            'git_deployment_id' => $gitDeploymentId,
            'branch'            => $branch,
            'stages'            => $this->sanitizeStages($data['stages'] ?? []),
            'enable_webhook'    => (bool) ($data['enable_webhook'] ?? true),
        ];
    }

    protected function sanitizeStages(array $stages): array
    {
        $clean = [];

        foreach ($stages as $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $name = trim((string) ($stage['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $id = (string) ($stage['id'] ?? '');
            if (! preg_match('/^[a-zA-Z0-9_-]{1,60}$/', $id)) {
                $id = 'stage_' . (count($clean) + 1);
            }

            $branchOverride = trim((string) ($stage['branch_override'] ?? ''));
            if ($branchOverride !== '' && (! preg_match(self::VALID_BRANCH, $branchOverride) || str_starts_with($branchOverride, '-'))) {
                $branchOverride = '';
            }

            $env = [];
            foreach ((array) ($stage['env'] ?? []) as $k => $v) {
                if (is_string($k) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $k)) {
                    $env[$k] = (string) $v;
                }
            }

            $clean[] = [
                'id'              => $id,
                'name'            => mb_substr($name, 0, 120),
                'command'         => (string) ($stage['command'] ?? ''),
                'script'          => (string) ($stage['script'] ?? ''),
                'branch_override' => $branchOverride,
                'env'             => $env,
            ];
        }

        return $clean;
    }

    protected function executeStages(DeployPipeline $pipeline, array $stages, ?callable $onOutput): array
    {
        $docRoot = $this->documentRoot($pipeline);
        $sudoUser = (string) config('larapanel.server.sudo_user', 'www-data');

        if (! is_dir($docRoot)) {
            $this->sudo->run(['mkdir', '-p', $docRoot], false);
        }
        $this->sudo->run(['chown', $sudoUser . ':' . $sudoUser, $docRoot], false);

        $results = [];

        foreach ($stages as $stage) {
            $name = $stage['name'];
            $started = microtime(true);

            if ($onOutput) {
                $onOutput("\n=== Etapa [{$name}] ===\n");
            }

            try {
                $log = $this->runStage($sudoUser, $docRoot, $stage, $onOutput);
                $ok = true;
            } catch (\Throwable $e) {
                $log = $e->getMessage();
                $ok = false;
            }

            $results[] = [
                'stage'       => $stage['id'],
                'name'        => $name,
                'ok'          => $ok,
                'log'         => $log,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];

            if (! $ok) {
                if ($onOutput) {
                    $onOutput("!!! La etapa [{$name}] falló. El pipeline se detiene.\n");
                }
                break;
            }
        }

        return $results;
    }

    /**
     * Run a single stage safely. The stage body (command + script) is written
     * to a generated temp file, copied into /tmp owned by the sudo user via
     * SudoExecutor cp/chown, and executed with `['bash', $tmpFile]` through a
     * non-sudo ShellExecutor so the process keeps the php-fpm/www-data
     * identity (same as GitService deploy scripts).
     */
    protected function runStage(string $sudoUser, string $docRoot, array $stage, ?callable $onOutput): string
    {
        $command = trim((string) ($stage['command'] ?? ''));
        $script = trim((string) ($stage['script'] ?? ''));

        if ($command === '' && $script === '') {
            return 'La etapa no define <command> ni <script>.';
        }

        $body = $command !== '' ? $command : '';
        if ($script !== '') {
            $body = $body !== '' ? $body . "\n" . $script : $script;
        }

        $branchOverride = (string) $stage['branch_override'];
        if ($branchOverride !== '') {
            $body = "git fetch origin {$branchOverride} --quiet\n"
                  . "git reset --hard origin/{$branchOverride} --quiet\n"
                  . $body;
        }

        $tmpStorage = storage_path('app/tmp/lp_stage_' . bin2hex(random_bytes(6)) . '.sh');
        @mkdir(dirname($tmpStorage), 0755, true);
        file_put_contents($tmpStorage, "#!/usr/bin/env bash\nset -o pipefail\n" . $body . "\n");

        $tmpRun = '/tmp/' . basename($tmpStorage);

        try {
            $this->sudo->run(['cp', $tmpStorage, $tmpRun]);
            $this->sudo->run(['chown', $sudoUser . ':' . $sudoUser, $tmpRun]);

            $result = app(ShellExecutor::class)
                ->withTimeout(600)
                ->withEnv(array_merge((array) getenv(), (array) ($stage['env'] ?? [])))
                ->inDirectory($docRoot)
                ->run(['bash', $tmpRun], false);

            $log = trim($result->stdout . "\n" . $result->stderr);

            if ($onOutput && $log !== '') {
                foreach (explode("\n", $log) as $line) {
                    $onOutput($line . "\n");
                }
            }

            if (! $result->successful()) {
                throw new \RuntimeException("La etapa terminó con código de salida {$result->exitCode}.");
            }

            return $log !== '' ? $log : "Etapa finalizada con éxito (código 0).";
        } finally {
            try {
                $this->sudo->run(['rm', '-f', $tmpRun], false);
            } catch (\Throwable) {
                // Best-effort cleanup.
            }
            @unlink($tmpStorage);
        }
    }

    protected function simulateStages(DeployPipeline $pipeline, array $stages, string $trigger, ?callable $onOutput): array
    {
        $results = [];
        $samples = [
            'composer install --no-interaction --prefer-dist --optimize-autoloader',
            '> Illuminate\Foundation\ComposerScripts::postAutoloadDump',
            'Generating optimized autoload files',
            'php artisan migrate --force',
            'Nothing to migrate.',
            'npm run build',
            '> vite build',
            '✓ 34 modules transformed.',
        ];

        foreach ($stages as $stage) {
            $started = microtime(true);
            usleep(250000);

            $body = trim((string) ($stage['command'] ?? '') . "\n" . (string) ($stage['script'] ?? ''));
            $lines = [$stage['command'] ?? $stage['name'], ...$samples];
            if ($stage['branch_override']) {
                array_unshift($lines, "git fetch origin {$stage['branch_override']} --quiet", "HEAD is now at " . substr(md5(rand()), 0, 7) . " Simulacro");
            }

            $log = implode("\n", $lines);
            if ($onOutput) {
                $onOutput("\n=== Etapa [{$stage['name']}] ===\n" . $log . "\n");
            }

            $results[] = [
                'stage'       => $stage['id'],
                'name'        => $stage['name'],
                'ok'          => true,
                'log'         => $log,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        return $results;
    }
}