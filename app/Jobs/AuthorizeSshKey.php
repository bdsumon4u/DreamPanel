<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AuthorizeSshKey implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Site $site,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->site->update(['status' => SiteStatus::DEPLOYING]);

        Log::info('Starting SSH key authorization', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
            'parent_site_id' => $this->site->parent_id,
        ]);

        if (! $this->site->parent) {
            Log::warning('Skipping SSH key authorization because parent site is missing', [
                'site_id' => $this->site->id,
                'domain' => $this->site->domain,
            ]);

            return;
        }

        ($parentHosting = $this->site->parent->hosting)->copySshKey();
        if ($parentHosting->isNot($this->site->hosting)) {
            $this->site->hosting->copySshKey();
        }

        Log::info('SSH key authorization completed', [
            'site_id' => $this->site->id,
            'domain' => $this->site->domain,
        ]);
    }
}
