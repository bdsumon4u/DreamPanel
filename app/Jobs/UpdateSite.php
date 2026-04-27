<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Ssh\Ssh;

class UpdateSite implements ShouldQueue
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
            Log::info('Updating site '.$this->site->name.' on '.$this->site->domain);
            $process = Ssh::create($this->site->hosting->username, $this->site->hosting->connectionIp())
                ->usePrivateKey(Storage::disk('local')->path('HOTASH'))
                ->disablePasswordAuthentication()
                ->disableStrictHostKeyChecking()
                ->setTimeout(700)
                ->execute([
                    'cd '.$this->site->directory,
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
