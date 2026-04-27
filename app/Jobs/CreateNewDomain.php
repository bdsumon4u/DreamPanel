<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\HostingProviders\HostingProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateNewDomain implements ShouldQueue
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
        Log::info('Starting domain creation', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'hosting_id' => $this->site->hosting_id,
        ]);

        try {
            app(HostingProviderResolver::class)
                ->resolve($this->site->hosting)
                ->createNewDomain($this->site);

            Log::info('Domain creation completed', [
                'site_id' => $this->site->id,
                'domain' => $this->site->domain,
            ]);
        } catch (Throwable $e) {
            $this->site->update(['status' => SiteStatus::DEPLOY_FAILED]);

            Log::error('Domain creation failed', [
                'site_id' => $this->site->id,
                'domain' => $this->site->domain,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
