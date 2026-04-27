<?php

namespace App\Actions;

use App\Enums\SiteStatus;
use App\Jobs\AuthorizeSshKey;
use App\Jobs\CopySiteFromParent;
use App\Jobs\CreateDatabaseAndUser;
use App\Jobs\CreateEmailAccount;
use App\Jobs\CreateNewDomain;
use App\Models\Site;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeploySite
{
    public function handle(Site $site): void
    {
        $site->update(['status' => SiteStatus::PENDING, 'deleted_at' => null]);

        Bus::chain([
            new AuthorizeSshKey($site),
            new CreateNewDomain($site),
            new CreateEmailAccount($site),
            new CreateDatabaseAndUser($site),
            new CopySiteFromParent($site),
        ])
            ->catch(function (Throwable $e) use ($site) {
                Log::error('Site deployment chain failed', [
                    'site_id' => $site->id,
                    'domain' => $site->domain,
                    'error' => $e->getMessage(),
                ]);
            })
            ->onQueue('high')
            ->dispatch();
    }
}
