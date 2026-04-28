<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Jobs\Traits\CanDelete;
use App\Models\Site;
use App\Services\HostingProviders\CloudPanelProvider;
use App\Services\HostingProviders\HostingProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteDomain implements ShouldQueue
{
    use CanDelete, Queueable;

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
        if (! $this->canDelete()) {
            return;
        }

        $this->site->update(['status' => SiteStatus::DELETING]);

        $provider = app(HostingProviderResolver::class)->resolve($this->site->hosting);

        try {
            Log::info('Starting deletion process', [
                'domain' => $this->site->domain,
                'hosting_id' => $this->site->hosting_id,
                'directory' => $this->site->directory,
            ]);

            $provider->deleteDomain($this->site);

            if ($provider instanceof CloudPanelProvider) {
                $this->site->update([
                    'status' => SiteStatus::DELETED,
                    'deleted_at' => now(),
                ]);
            } else {
                $this->site->delete();
            }

            Log::info('Site deletion completed', [
                'domain' => $this->site->domain,
                'site_id' => $this->site->id,
            ]);
        } catch (ConnectionException $e) {
            Log::error('Connection error while deleting site', [
                'domain' => $this->site->domain,
                'error' => $e->getMessage(),
            ]);

            $this->site->update(['status' => SiteStatus::DELETE_FAILED]);

            throw $e;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'does not exist')) {
                Log::warning('Skipping site deletion because the remote domain no longer exists', [
                    'domain' => $this->site->domain,
                    'site_id' => $this->site->id,
                    'error' => $e->getMessage(),
                ]);

                if ($provider instanceof CloudPanelProvider) {
                    $this->site->update(['status' => SiteStatus::DELETED, 'deleted_at' => now()]);
                } else {
                    $this->site->delete();
                }

                return;
            }

            Log::error('Error deleting site', [
                'domain' => $this->site->domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->site->update(['status' => SiteStatus::DELETE_FAILED]);

            throw $e;
        }
    }
}
