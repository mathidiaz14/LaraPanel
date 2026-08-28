<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Prunes expired phpMyAdmin SSO token files left in the temp directory.
 *
 * The phpMyAdmin sign-on handoff writes "user:pass" into a temp file that is
 * consumed by PMA on the redirect; this command deletes tokens older than the
 * configured grace period so DB credentials never linger indefinitely.
 */
class CleanupPmaSsoCommand extends Command
{
    protected $signature = 'larapanel:cleanup-pma-sso {--age=300 : Max age in seconds before deletion}';
    protected $description = 'Remove stale phpMyAdmin SSO token files from temp';

    public function handle(): int
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'larapanel_pma_sso';

        if (!is_dir($dir)) {
            return self::SUCCESS;
        }

        $maxAge = (int) $this->option('age');
        $now = time();
        $deleted = 0;

        foreach (File::files($dir) as $file) {
            if ($now - $file->getMTime() > $maxAge) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale phpMyAdmin SSO token file(s).");

        return self::SUCCESS;
    }
}
