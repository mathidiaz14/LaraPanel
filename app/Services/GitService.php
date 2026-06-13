<?php

namespace App\Services;

use App\Models\GitDeployment;
use App\Models\GitDeploymentLog;
use Illuminate\Support\Facades\Process;

class GitService
{
    /**
     * Executes a deployment for a given configuration.
     * Can be triggered manually or via webhook.
     */
    public function deploy(GitDeployment $deployment, string $triggeredBy = 'manual', ?string $commitHash = null): GitDeploymentLog
    {
        $log = $deployment->logs()->create([
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'commit_hash'  => $commitHash,
        ]);

        if (!app()->isProduction()) {
            return $this->simulateDeploy($deployment, $log);
        }

        $domainPath = $deployment->deploy_path ?: '/var/www/' . $deployment->domain_name . '/public_html';
        $outputBuffer = ">>> Starting deployment for {$deployment->domain_name}\n";
        $outputBuffer .= ">>> Repository: {$deployment->repository_url} | Branch: {$deployment->branch}\n\n";

        // Check if directory is a git repository
        $isRepo = Process::path($domainPath)->run('git rev-parse --is-inside-work-tree');

        try {
            if ($isRepo->successful()) {
                // Already a repo, fetch and pull
                $outputBuffer .= ">>> Repository found. Pulling latest changes...\n";
                $result = Process::path($domainPath)
                    ->timeout(120)
                    ->run("git fetch origin {$deployment->branch} && git reset --hard origin/{$deployment->branch}");
                $outputBuffer .= $result->output() . $result->errorOutput() . "\n";
                if (!$result->successful()) throw new \Exception('Git pull failed.');
            } else {
                // Not a repo, clone it
                $outputBuffer .= ">>> Directory is not a repository. Cloning...\n";
                // Warning: cloning into an existing directory might fail if not empty.
                $result = Process::path(dirname($domainPath))
                    ->timeout(120)
                    ->run("git clone -b {$deployment->branch} {$deployment->repository_url} public_html_tmp && mv public_html_tmp/.git {$domainPath}/.git && rm -rf public_html_tmp && cd {$domainPath} && git reset --hard");
                $outputBuffer .= $result->output() . $result->errorOutput() . "\n";
                if (!$result->successful()) throw new \Exception('Git clone failed.');
            }

            // Execute custom deploy script if provided
            if (!empty($deployment->deploy_script)) {
                $outputBuffer .= "\n>>> Executing custom deployment script...\n";
                // Write script to a temporary file, run it, and delete it
                $scriptPath = $domainPath . '/.deploy_script.sh';
                file_put_contents($scriptPath, $deployment->deploy_script);
                chmod($scriptPath, 0755);
                
                $result = Process::path($domainPath)
                    ->timeout(300)
                    ->run('./.deploy_script.sh');
                    
                $outputBuffer .= $result->output() . $result->errorOutput() . "\n";
                unlink($scriptPath);

                if (!$result->successful()) throw new \Exception('Deploy script failed.');
            }

            $outputBuffer .= "\n>>> Deployment completed successfully.\n";
            $status = 'success';

            // Get last commit info
            $commitInfo = Process::path($domainPath)->run('git log -1 --pretty=format:"%H|%s"');
            if ($commitInfo->successful()) {
                $parts = explode('|', $commitInfo->output(), 2);
                $log->commit_hash = $parts[0] ?? $commitHash;
                $log->commit_message = $parts[1] ?? null;
            }

        } catch (\Throwable $e) {
            $outputBuffer .= "\n>>> ERROR: " . $e->getMessage() . "\n";
            $status = 'failed';
        }

        $log->update([
            'status' => $status,
            'output' => $outputBuffer,
        ]);

        $deployment->update(['last_deployed_at' => now()]);

        return $log;
    }

    protected function simulateDeploy(GitDeployment $deployment, GitDeploymentLog $log): GitDeploymentLog
    {
        // Simulate a 3-second delay
        sleep(3);
        
        $output = ">>> Starting simulated deployment for {$deployment->domain_name}\n";
        $output .= ">>> Repository: {$deployment->repository_url} | Branch: {$deployment->branch}\n\n";
        $output .= "git fetch origin {$deployment->branch}\n";
        $output .= "From https://github.com/larapanel/simulated-repo\n";
        $output .= " * branch            main       -> FETCH_HEAD\n";
        $output .= "git reset --hard origin/{$deployment->branch}\n";
        $output .= "HEAD is now at " . substr(md5(rand()), 0, 7) . " Simulated commit message\n";
        
        if (!empty($deployment->deploy_script)) {
            $output .= "\n>>> Executing custom deployment script...\n";
            $output .= "composer install --no-interaction --prefer-dist --optimize-autoloader\n";
            $output .= "Generating optimized autoload files\n";
            $output .= "> Illuminate\Foundation\ComposerScripts::postAutoloadDump\n";
            $output .= "php artisan migrate --force\n";
            $output .= "Nothing to migrate.\n";
            $output .= "npm run build\n";
            $output .= "> build\n> vite build\n\n✓ 34 modules transformed.\n";
        }

        $output .= "\n>>> Deployment completed successfully.\n";

        $log->update([
            'status'         => 'success',
            'output'         => $output,
            'commit_hash'    => substr(md5(rand()), 0, 40),
            'commit_message' => 'Simulated automatic commit from web editor',
        ]);

        $deployment->update(['last_deployed_at' => now()]);

        return $log;
    }
}
