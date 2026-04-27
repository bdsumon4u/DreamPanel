<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\HostingProviders\Contracts\HasEmailSupport;
use App\Services\HostingProviders\HostingProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateEmailAccount implements ShouldQueue
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
        Log::info('Starting email account creation', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'hosting_id' => $this->site->hosting_id,
        ]);

        $provider = app(HostingProviderResolver::class)
            ->resolve($this->site->hosting);

        if (! $provider instanceof HasEmailSupport) {
            Log::info('Skipping email account creation: provider does not support email', [
                'site_id' => $this->site->id,
                'provider' => $provider::class,
            ]);

            return;
        }

        try {
            $provider->createOrUpdateEmailAccount($this->site);

            Log::info('Email account creation completed', [
                'site_id' => $this->site->id,
                'domain' => $this->site->domain,
            ]);
        } catch (Throwable $e) {
            $this->site->update(['status' => SiteStatus::DEPLOY_FAILED]);

            Log::error('Email account creation failed', [
                'site_id' => $this->site->id,
                'domain' => $this->site->domain,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
