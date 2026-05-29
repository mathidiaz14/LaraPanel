<?php

namespace App\Http\Controllers;

use App\Models\GitDeployment;
use App\Services\GitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitWebhookController extends Controller
{
    public function handle(Request $request, string $uuid, GitService $gitService)
    {
        $deployment = GitDeployment::where('webhook_id', $uuid)->firstOrFail();

        if (!$deployment->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy is disabled for this repository'], 400);
        }

        // Validate secret if provided (GitHub/GitLab signature validation)
        // For simplicity in this implementation, we check if a secret was configured
        // and if it matches X-Hub-Signature-256 or X-Gitlab-Token
        if (!empty($deployment->webhook_secret)) {
            $githubSignature = $request->header('X-Hub-Signature-256');
            $gitlabToken     = $request->header('X-Gitlab-Token');

            $valid = false;

            if ($githubSignature) {
                // GitHub: sha256=HASH
                $payload = $request->getContent();
                $expected = 'sha256=' . hash_hmac('sha256', $payload, $deployment->webhook_secret);
                $valid = hash_equals($expected, $githubSignature);
            } elseif ($gitlabToken) {
                // GitLab
                $valid = hash_equals($deployment->webhook_secret, $gitlabToken);
            } else {
                // No signature provided but secret is required
                // Allow simple testing by passing ?secret=...
                if ($request->query('secret') === $deployment->webhook_secret) {
                    $valid = true;
                }
            }

            if (!$valid) {
                Log::warning("Invalid webhook signature for deployment {$deployment->id}");
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        }

        // Extract branch from payload to ensure it matches our configured branch
        $branch = 'main'; // default fallback
        
        // GitHub: ref -> refs/heads/main
        if ($request->has('ref')) {
            $ref = $request->input('ref');
            $branch = str_replace('refs/heads/', '', $ref);
        }
        // GitLab: object_kind -> push, ref -> refs/heads/main
        elseif ($request->input('object_kind') === 'push') {
            $ref = $request->input('ref');
            $branch = str_replace('refs/heads/', '', $ref);
        }

        if ($branch !== $deployment->branch) {
            return response()->json(['message' => "Push to branch {$branch} ignored. Tracking branch is {$deployment->branch}."]);
        }

        // Get latest commit hash for the log
        $commitHash = null;
        if ($request->has('head_commit.id')) {
            $commitHash = $request->input('head_commit.id'); // GitHub
        } elseif ($request->has('checkout_sha')) {
            $commitHash = $request->input('checkout_sha'); // GitLab
        }

        // We dispatch this synchronously for the simulation, but in production 
        // this should be dispatched to a Job queue (e.g. DeployGitRepository::dispatch)
        // to avoid timeout and blocking the webhook response.
        $gitService->deploy($deployment, 'webhook', $commitHash);

        return response()->json(['message' => 'Deployment triggered successfully']);
    }
}
