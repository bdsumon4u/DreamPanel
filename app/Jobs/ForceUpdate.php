<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Spatie\Ssh\Ssh;

class ForceUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Site $site,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->site->update(['status' => SiteStatus::UPDATING]);

        try {
            $this->site->hosting->copySshKey();
            $process = Ssh::create($this->site->hosting->username, $this->site->hosting->connectionIp())
                ->usePrivateKey(Storage::disk('local')->path('HOTASH'))
                ->disablePasswordAuthentication()
                ->disableStrictHostKeyChecking()
                ->setTimeout(1000)
                ->execute([
                    'cd '.$this->site->directory,
                    'origin_file=$(mktemp) && git remote get-url origin > "$origin_file" 2>/dev/null || true',
                    'rm -rf .git',
                    'git init',
                    'if [ -s "$origin_file" ]; then git remote add origin "$(cat "$origin_file")"; fi',
                    'rm -f "$origin_file"',
                    'git fetch',
                    'git clean -fd -e .env -e storage/app/public',
                    'rm -f storage/app/public/.gitignore',
                    'git pull origin master',

                    // Check and update/add CACHE_DRIVER
                    'grep -q "^CACHE_DRIVER=" .env && sed -i "s/^CACHE_DRIVER=.*/CACHE_DRIVER=database/" .env || echo "CACHE_DRIVER=database" >> .env',
                    // Check and update/add SCOUT_DRIVER
                    'grep -q "^SCOUT_DRIVER=" .env && sed -i "s/^SCOUT_DRIVER=.*/SCOUT_DRIVER=database/" .env || echo "SCOUT_DRIVER=database" >> .env',
                    // Check and update/add APP_TIMEZONE
                    'grep -q "^APP_TIMEZONE=" .env && sed -i "s/^APP_TIMEZONE=.*/APP_TIMEZONE=Asia\/Dhaka/" .env || echo "APP_TIMEZONE=Asia/Dhaka" >> .env',

                    './server_deploy.sh',
                ]);

            if (! $process->isSuccessful()) {
                $this->site->update(['status' => SiteStatus::UPDATE_FAILED]);
                $errorOutput = trim($process->getErrorOutput());
                $standardOutput = trim($process->getOutput());
                $exitCode = $process->getExitCode();

                throw new \RuntimeException(
                    'SSH command failed. Exit code: '.$exitCode
                    .' Error output: '.($errorOutput !== '' ? $errorOutput : '[none]')
                    .' Standard output: '.($standardOutput !== '' ? $standardOutput : '[none]')
                );
            }
            $this->site->update(['status' => SiteStatus::SITE_ACTIVE]);
        } catch (\Exception $e) {
            $this->site->update(['status' => SiteStatus::UPDATE_FAILED]);
            // Log the exception message to capture more details
            throw new \RuntimeException('SSH connection failed, please check the server and public key setup. Error: '.$e->getMessage());
        }
    }
}
