<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Ssh\Ssh;

class CopySiteFromParent implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

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
        Log::info('Deploying site '.$this->site->name.' to '.$this->site->domain);
        $process = Ssh::create($this->site->parent->hosting->username, $this->site->parent->hosting->connectionIp())
            ->usePrivateKey(Storage::disk('local')->path('HotashTech'))
            ->disablePasswordAuthentication()
            ->disableStrictHostKeyChecking()
            ->setTimeout(1000)
            ->execute([
                'cd '.$this->site->parent->full_directory,
                './copy.sh '.collect($config = [
                    '-s' => $this->site->name,
                    '-d' => $this->site->domain,
                    '-h' => $this->site->hosting->connectionIp(),
                    '-u' => $this->site->username,
                    '-su' => $this->site->hosting->username,
                    '-db' => $this->site->effective_database_name,
                    '-dbu' => $this->site->effective_database_user,
                    '-dbp' => $this->site->database_pass,
                    '-mu' => $this->site->email_username ?? 'sites@hotash.tech',
                    '-mp' => $this->site->email_password ?? 'sites@hotash.tech',
                    '-r' => $this->site->full_directory,
                ])
                    ->flatMap(fn ($val, $key) => [$key, '"'.$val.'"'])
                    ->implode(' '),
            ]);

        Log::info('Copy process output:', [
            'parent_username' => $this->site->parent->hosting->username,
            'parent_ip' => $this->site->parent->hosting->connectionIp(),
            'private_key_path' => Storage::disk('local')->path('HotashTech'),
            'private_key_content' => Storage::disk('local')->get('HotashTech'),
            ...$config,
            'parent_directory' => $this->site->parent->full_directory,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
            'successful' => $process->isSuccessful(),
        ]);

        if (! $process->isSuccessful()) {
            $this->site->update(['status' => SiteStatus::DEPLOY_FAILED]);
            Log::error('Copy failed for site '.$this->site->name, [
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);
            throw new \Exception($process->getErrorOutput());
        }

        $this->site->update(['status' => SiteStatus::SITE_ACTIVE]);
        Log::info('Site '.$this->site->name.' deployed successfully to '.$this->site->domain);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [100, 500, 1000];
    }
}
