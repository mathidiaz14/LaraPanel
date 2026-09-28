<?php

namespace App\Console\Commands;

use App\Services\HealthScoreService;
use App\Shell\ServerContext;
use Illuminate\Console\Command;

class ComputeHealthScoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panel:health-score';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute the composite server health score and store a snapshot';

    /**
     * Execute the console command.
     */
    public function handle(HealthScoreService $healthService): int
    {
        try {
            $snapshot = $healthService->persistSnapshot(ServerContext::server());

            $this->info("Health score computed: {$snapshot->score} ({$snapshot->grade})");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Could not compute health score: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}