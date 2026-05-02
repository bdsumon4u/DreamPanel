<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckSiteStatus implements ShouldQueue
{
    use Batchable, Queueable;

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
        $this->site->refresh();

        $reachableAndOk = false;

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(10)->head('http://'.$this->site->domain);
            $reachableAndOk = $response->ok();
        } catch (\Exception $e) {
            //
        }

        if ($this->site->laravel_maintenance_mode) {
            if ($reachableAndOk) {
                $this->site->update([
                    'laravel_maintenance_mode' => false,
                    'status' => SiteStatus::SITE_ACTIVE,
                ]);
            }

            return;
        }

        $status = $reachableAndOk ? SiteStatus::SITE_ACTIVE : SiteStatus::SITE_DOWN;

        Log::info("Site: {$this->site->domain} - Status: {$status->value}");

        if ($this->site->status != $status) {
            $this->site->update(['status' => $status]);
        }
    }
}
