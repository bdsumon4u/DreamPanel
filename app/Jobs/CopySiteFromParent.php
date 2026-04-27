<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->usePrivateKey(Storage::disk('local')->path('HOTASH'))
            ->disablePasswordAuthentication()
            ->disableStrictHostKeyChecking()
            ->setTimeout(1000)
            ->execute([
                'cd '.$this->site->parent->directory,
                './copy.sh '.collect([
                    '-s' => $this->site->name,
                    '-d' => $this->site->domain,
                    '-h' => $this->site->hosting->connectionIp(),
                    '-u' => $this->site->username,
                    '-su' => $this->site->username,
                    '-db' => $this->site->database_name,
                    '-dbu' => $this->site->database_user,
                    '-dbp' => $this->site->database_pass,
                    '-mu' => $this->site->email_username,
                    '-mp' => $this->site->email_password,
                    '-r' => $this->site->directory,
                ])
                    ->flatMap(fn ($val, $key) => [$key, '"'.$val.'"'])
                    ->implode(' '),
            ]);

        Log::info('Copy process output:', [
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

    private function generateSiteUser(Site $site): string
    {
        $maxLength = 24;
        $suffix = dechex((int) $site->id);
        $suffix = $suffix !== '' ? $suffix : substr(md5($site->domain), 0, 6);
        $suffix = Str::lower(preg_replace('/[^a-z0-9]/', '', $suffix) ?? '');

        $base = Str::lower($site->domain);
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?? '';

        if ($base === '' || ctype_digit($base[0])) {
            $base = 'site'.$base;
        }

        $availableBaseLength = max(1, $maxLength - strlen($suffix));
        $base = substr($base, 0, $availableBaseLength);
        $username = substr($base.$suffix, 0, $maxLength);

        if ($username === '' || ctype_digit($username[0])) {
            $username = 's'.substr($username, 0, $maxLength - 1);
        }

        return $username;
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
