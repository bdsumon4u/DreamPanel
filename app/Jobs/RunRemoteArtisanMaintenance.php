<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Ssh\Ssh;

class RunRemoteArtisanMaintenance implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Site $site,
        public string $mode,
    ) {
        if (! in_array($mode, ['up', 'down'], true)) {
            throw new \InvalidArgumentException('Mode must be "up" or "down".');
        }
    }

    public function handle(): void
    {
        $site = Site::query()->with('hosting')->find($this->site->id);
        if ($site === null) {
            return;
        }

        $site->hosting->copySshKey();

        $artisanLine = $this->mode === 'down'
            ? './php artisan down --no-interaction'
            : './php artisan up';

        Log::info('Running remote artisan maintenance', [
            'site_id' => $site->id,
            'domain' => $site->domain,
            'mode' => $this->mode,
            'command' => $artisanLine,
        ]);

        $process = Ssh::create($site->hosting->username, $site->hosting->connectionIp())
            ->usePrivateKey(Storage::disk('local')->path('HotashTech'))
            ->disablePasswordAuthentication()
            ->disableStrictHostKeyChecking()
            ->usePort($site->hosting->sshPort())
            ->setTimeout(120)
            ->execute([
                'cd '.$site->full_directory,
                $artisanLine,
            ]);

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $message = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'No output.');

            Log::error('Remote artisan maintenance failed', [
                'site_id' => $site->id,
                'mode' => $this->mode,
                'message' => $message,
            ]);

            throw new \RuntimeException('Remote artisan '.$this->mode.' failed: '.$message);
        }

        Log::info('Remote artisan maintenance completed', [
            'site_id' => $site->id,
            'mode' => $this->mode,
            'output' => trim($process->getOutput()),
        ]);

        $site->update([
            'laravel_maintenance_mode' => $this->mode === 'down',
        ]);
    }
}
