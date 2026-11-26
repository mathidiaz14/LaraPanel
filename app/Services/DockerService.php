<?php

namespace App\Services;

use App\Shell\SudoExecutor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DockerService
{
    // Docker Go template for JSON output — safe for parsing
    private const CONTAINER_FORMAT = '{"id":"{{.ID}}","name":"{{.Names}}","image":"{{.Image}}","status":"{{.Status}}","state":"{{.State}}","ports":"{{.Ports}}","created":"{{.CreatedAt}}","size":"{{.Size}}","project":"{{.Label "com.docker.compose.project"}}"}';
    private const IMAGE_FORMAT     = '{"id":"{{.ID}}","repo":"{{.Repository}}","tag":"{{.Tag}}","size":"{{.Size}}","created":"{{.CreatedAt}}"}';

    public function __construct(
        protected SudoExecutor $shell
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    //   CONTAINERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List all containers (running + stopped).
     */
    public function listContainers(): array
    {
        try {
            $result = $this->docker(['ps', '-a', '--format', self::CONTAINER_FORMAT]);
            return $this->parseJsonLines($result->output());
        } catch (\Throwable $e) {
            Log::error('DockerService::listContainers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get a single container's details by name or ID.
     */
    public function inspectContainer(string $nameOrId): array
    {
        $this->validateName($nameOrId);
        try {
            $result = $this->docker(['inspect', '--format', '{{json .}}', $nameOrId]);
            $data = json_decode($result->output(), true);
            return $data[0] ?? $data ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get live stats for a container (single shot, no streaming).
     */
    public function containerStats(string $nameOrId): array
    {
        $this->validateName($nameOrId);
        try {
            $result = $this->docker([
                'stats', '--no-stream', '--format',
                '{"cpu":"{{.CPUPerc}}","mem":"{{.MemUsage}}","mem_pct":"{{.MemPerc}}","net":"{{.NetIO}}","block":"{{.BlockIO}}"}',
                $nameOrId,
            ]);
            return json_decode($result->output(), true) ?? [];
        } catch (\Throwable $e) {
            return ['cpu' => 'N/A', 'mem' => 'N/A', 'mem_pct' => 'N/A'];
        }
    }

    /**
     * Start a container.
     */
    public function start(string $nameOrId): bool
    {
        $this->validateName($nameOrId);
        return $this->docker(['start', $nameOrId])->successful();
    }

    /**
     * Stop a container (graceful with 10s timeout).
     */
    public function stop(string $nameOrId): bool
    {
        $this->validateName($nameOrId);
        return $this->docker(['stop', '-t', '10', $nameOrId])->successful();
    }

    /**
     * Restart a container.
     */
    public function restart(string $nameOrId): bool
    {
        $this->validateName($nameOrId);
        return $this->docker(['restart', $nameOrId])->successful();
    }

    /**
     * Force-remove a container (stops first if running).
     */
    public function remove(string $nameOrId): bool
    {
        $this->validateName($nameOrId);
        return $this->docker(['rm', '-f', $nameOrId])->successful();
    }

    /**
     * Get the last N log lines from a container.
     */
    public function logs(string $nameOrId, int $lines = 100): string
    {
        $this->validateName($nameOrId);
        try {
            $result = $this->shell
                ->withTimeout(15)
                ->run(['docker', 'logs', '--tail', (string)$lines, '--timestamps', $nameOrId], checkExit: false);
            // Docker writes logs to stderr by default, merge both
            return trim(($result->stdout ?: '') . "\n" . ($result->stderr ?: ''));
        } catch (\Throwable $e) {
            return "Error reading logs: " . $e->getMessage();
        }
    }

    public function execContainerCommand(string $container, string $command): string
    {
        $this->validateName($container);

        try {
            // Using sh -c allows passing the full command string properly, and it's standard in most alpine/debian images
            $result = $this->shell
                ->withTimeout(300)
                ->run(['docker', 'exec', '-i', $container, 'sh', '-c', $command], checkExit: false);
            return trim(($result->stdout ?: '') . "\n" . ($result->stderr ?: ''));
        } catch (\Throwable $e) {
            return "Error executing command: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   IMAGES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List all local images.
     */
    public function listImages(): array
    {
        try {
            $result = $this->docker(['images', '--format', self::IMAGE_FORMAT]);
            return $this->parseJsonLines($result->output());
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Pull an image from Docker Hub or any registry.
     * Returns the full command output for displaying to the user.
     */
    public function pullImage(string $image): string
    {
        // Validate image name: only allow alphanum, colons, slashes, dots, dashes
        if (!preg_match('/^[a-zA-Z0-9\/_\-.:]+$/', $image)) {
            throw new \InvalidArgumentException("Nombre de imagen no válido: {$image}");
        }

        try {
            $result = $this->shell->withTimeout(300)->run(['docker', 'pull', $image], checkExit: false);
            return trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Remove a local image by name:tag or ID.
     */
    public function removeImage(string $image): bool
    {
        if (!preg_match('/^[a-zA-Z0-9\/_\-.:]+$/', $image)) {
            throw new \InvalidArgumentException("Imagen no válida.");
        }
        return $this->docker(['rmi', $image], checkExit: false)->successful();
    }

    /**
     * Prune all unused images to free disk space.
     */
    public function pruneImages(): string
    {
        try {
            $result = $this->docker(['image', 'prune', '-f']);
            return trim($result->output());
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   DOCKER COMPOSE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Write docker-compose.yml content to disk and run `docker compose up -d`.
     */
    public function composeUp(string $stackName, string $yamlContent): string
    {
        $this->validateStackName($stackName);
        $path = $this->getComposePath($stackName);
        $file = $path . '/docker-compose.yml';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        file_put_contents($file, $yamlContent);

        try {
            $result = $this->shell
                ->withTimeout(300)
                ->inDirectory($path)
                ->run(['docker', 'compose', '-p', $this->normalizeProjectName($stackName), '-f', $file, 'up', '-d', '--remove-orphans'], checkExit: false);

            return trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Stop and remove containers in a Compose stack.
     */
    public function composeDown(string $stackName): string
    {
        $this->validateStackName($stackName);
        $path = $this->getComposePath($stackName);
        $file = $path . '/docker-compose.yml';

        if (!file_exists($file)) {
            return "Archivo de Compose no encontrado para el stack '{$stackName}'.";
        }

        try {
            $result = $this->shell
                ->withTimeout(60)
                ->run(['docker', 'compose', '-p', $this->normalizeProjectName($stackName), '-f', $file, 'down'], checkExit: false);
            return trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    /**
     * Get logs from a full Compose stack.
     */
    public function composeLogs(string $stackName, int $lines = 100): string
    {
        $this->validateStackName($stackName);
        $path = $this->getComposePath($stackName);
        $file = $path . '/docker-compose.yml';

        if (!file_exists($file)) {
            return "Archivo de Compose no encontrado para '{$stackName}'.";
        }

        try {
            $result = $this->shell
                ->withTimeout(15)
                ->run(['docker', 'compose', '-p', $this->normalizeProjectName($stackName), '-f', $file, 'logs', '--tail', (string)$lines, '--timestamps'], checkExit: false);
            return trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function listComposeProjects(): array
    {
        try {
            $result = $this->docker(['compose', 'ls', '--format', 'json']);
            $raw = trim($result->output());

            if ($raw === '') {
                return [];
            }

            if (str_starts_with($raw, '[')) {
                $decoded = json_decode($raw, true);
                $items = is_array($decoded) ? $decoded : [];
            } else {
                $items = $this->parseJsonLines($raw);
            }

            return collect($items)
                ->filter(fn ($item) => is_array($item))
                ->map(fn (array $item) => [
                    'name'        => (string) ($item['Name'] ?? $item['name'] ?? ''),
                    'status'      => (string) ($item['Status'] ?? $item['status'] ?? ''),
                    'configFiles' => (string) ($item['ConfigFiles'] ?? $item['configFiles'] ?? ''),
                ])
                ->filter(fn (array $item) => $item['name'] !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('DockerService::listComposeProjects failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function projectCommand(string $project, string $command, array $args = []): string
    {
        $this->validateName($project);

        $path = null;
        foreach ($this->listComposeProjects() as $item) {
            if (($item['name'] ?? null) !== $project) {
                continue;
            }

            $files = preg_split('/\s*,\s*/', (string) ($item['configFiles'] ?? '')) ?: [];
            if (!empty($files[0])) {
                $path = dirname($files[0]);
            }
            break;
        }

        if (!$path) {
            return "No se encontró la ruta del proyecto '{$project}'.";
        }

        return $this->runComposeInPath($path, $command, $args, $project);
    }

    public function getComposeContent(string $stackName): string
    {
        $this->validateStackName($stackName);
        $file = $this->getComposePath($stackName) . '/docker-compose.yml';
        return file_exists($file) ? file_get_contents($file) : '';
    }

    /**
     * Run a docker compose command in an arbitrary directory.
     */
    public function runComposeInPath(string $path, string $command, array $args = [], ?string $projectName = null): string
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("El directorio especificado no existe: {$path}");
        }

        if ($projectName !== null && trim($projectName) !== '') {
            $project = $this->normalizeProjectName($projectName);
        } else {
            $base = basename(rtrim($path, '/')) ?: 'app';
            $project = $this->normalizeProjectName($base . '-' . substr(sha1($path), 0, 8));
        }

        $cmd = array_merge(['docker', 'compose', '-p', $project, $command], $args);

        try {
            $result = $this->shell
                ->withTimeout(300)
                ->inDirectory($path)
                ->run($cmd, checkExit: false);

            return trim($result->stdout . "\n" . $result->stderr);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   SYSTEM
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if Docker daemon is installed and running.
     */
    public function isDaemonRunning(): bool
    {
        try {
            $result = $this->shell->run(['docker', 'info'], checkExit: false);
            return $result->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get Docker system disk usage summary.
     */
    public function systemDf(): string
    {
        try {
            $result = $this->docker(['system', 'df']);
            return trim($result->output());
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //   PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function docker(array $args, bool $checkExit = true): \App\Shell\ShellResult
    {
        return $this->shell->run(array_merge(['docker'], $args), $checkExit);
    }

    private function parseJsonLines(string $output): array
    {
        if (empty(trim($output))) {
            return [];
        }

        $results = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $results[] = $decoded;
            }
        }
        return $results;
    }

    private function normalizeProjectName(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name));
        $slug = trim($slug, '-_');

        if ($slug === '') {
            $slug = 'app';
        }

        if (!preg_match('/^[a-z0-9]/', $slug)) {
            $slug = 'app-' . $slug;
        }

        return substr($slug, 0, 60);
    }

    private function validateName(string $name): void
    {
        // Docker names/IDs: alphanumeric, underscores, dashes, dots
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) {
            throw new \InvalidArgumentException("Nombre de contenedor no válido: {$name}");
        }
    }

    private function validateStackName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            throw new \InvalidArgumentException("Nombre de stack no válido: {$name}");
        }
    }

    private function getComposePath(string $stackName): string
    {
        $base = config('larapanel.docker.compose_base_path', '/var/larapanel/compose');
        return rtrim($base, '/') . '/' . $stackName;
    }
}
