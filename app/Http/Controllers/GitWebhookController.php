<?php

namespace App\Http\Controllers;

use App\Models\GitDeployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class GitWebhookController extends Controller
{
    public function handle(Request $request, string $uuid)
    {
        $deployment = GitDeployment::where('webhook_id', $uuid)->firstOrFail();

        $signatureValid = $this->signatureIsValid($request, $deployment);

        // Test mode: used by the panel's "Probar Webhook" button to verify
        // connectivity + secret end-to-end without triggering a deploy.
        if ($request->header('X-Larapanel-Test') === '1') {
            return response()->json([
                'status'  => 'ok',
                'checks'  => [
                    'webhook_url'    => 'reachable',
                    'deployment'     => $deployment->domain_name,
                    'auto_deploy'    => $deployment->auto_deploy ? 'enabled' : 'disabled',
                    'secret'         => !empty($deployment->webhook_secret)
                        ? ($signatureValid ? 'valid' : 'invalid')
                        : 'not_configured',
                    'tracked_branch' => trim($deployment->branch ?: 'main'),
                ],
                'message' => $signatureValid
                    ? 'Webhook alcanzado correctamente y secreto validado.'
                    : 'Webhook alcanzado pero la firma del secreto NO es válida.',
            ], 200);
        }

        if (!$deployment->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy is disabled for this repository'], 400);
        }

        if (!empty($deployment->webhook_secret) && !$signatureValid) {
            Log::warning("Invalid webhook signature for deployment {$deployment->id}");
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Only push events trigger deployments (ignore pings, issue events, etc.)
        $event = $request->header('X-GitHub-Event');
        if ($event !== null && $event !== 'push') {
            return response()->json(['message' => "GitHub event '{$event}' ignored."]);
        }

        $objectKind = $request->input('object_kind'); // GitLab
        if ($event === null && $objectKind !== null && !in_array($objectKind, ['push', 'tag_push'])) {
            return response()->json(['message' => "GitLab event '{$objectKind}' ignored."]);
        }

        // Extract branch from payload to ensure it matches our configured branch
        // GitHub: ref -> refs/heads/main | GitLab: same format on push events
        $branch = null;
        if ($request->has('ref')) {
            $branch = str_replace('refs/heads/', '', $request->input('ref'));
        }

        if ($branch !== null && $branch !== trim($deployment->branch ?: 'main')) {
            return response()->json([
                'message' => "Push to branch {$branch} ignored. Tracking branch is {$deployment->branch}."
            ]);
        }

        // Get latest commit hash for the log
        $commitHash = $request->input('head_commit.id')       // GitHub
            ?? $request->input('checkout_sha');               // GitLab

        if ($commitHash !== null && !preg_match('/^[0-9a-f]{7,64}$/i', $commitHash)) {
            $commitHash = null;
        }

        // Launch the deployment detached from this request so the webhook
        // answers immediately (providers time out after ~10s and disable
        // hooks that answer slowly). Runs as the configured sudo user to
        // keep file ownership consistent with manual deployments.
        $this->spawnDeploy($deployment->id, $commitHash);

        return response()->json(['message' => 'Deployment triggered successfully']);
    }

    protected function spawnDeploy(int $deploymentId, ?string $commitHash): void
    {
        $php = (new PhpExecutableFinder())->find() ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/git-deploy.log');

        $runUser = config('larapanel.server.sudo_user', 'www-data');
        $useSudo = PHP_OS_FAMILY === 'Linux' && is_file('/usr/bin/sudo');

        $command = sprintf(
            ($useSudo ? 'sudo -n -u %s ' : '') . 'setsid nohup %s %s git:deploy %d --trigger=webhook %s >> %s 2>&1 &',
            escapeshellarg($runUser),
            escapeshellarg($php),
            escapeshellarg($artisan),
            $deploymentId,
            $commitHash ? '--commit=' . escapeshellarg($commitHash) : '',
            escapeshellarg($logFile),
        );

        Process::fromShellCommandline($command)->setTimeout(5)->run();

        Log::info("Git deploy spawned for deployment #{$deploymentId}" . ($commitHash ? " (commit {$commitHash})" : ''));
    }

    protected function signatureIsValid(Request $request, GitDeployment $deployment): bool
    {
        if (empty($deployment->webhook_secret)) {
            return true;
        }

        $githubSignature = $request->header('X-Hub-Signature-256');
        $gitlabToken     = $request->header('X-Gitlab-Token');

        if ($githubSignature) {
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $deployment->webhook_secret);

            return hash_equals($expected, $githubSignature);
        }

        if ($gitlabToken) {
            return hash_equals($deployment->webhook_secret, $gitlabToken);
        }

        // A secret is configured but the provider sent no signature header.
        return false;
    }
}
