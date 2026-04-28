<?php

namespace App\Console\Commands;

use App\Enums\HostingProvider;
use App\Enums\SiteStatus;
use App\Jobs\DeleteFiles;
use App\Models\Site;
use Illuminate\Console\Command;

class DispatchDeleteFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:delete-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to delete files for sites marked for deletion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Site::onlyTrashed()
            ->where('status', SiteStatus::DELETING)
            ->whereHas('hosting', fn ($query) => $query->where('provider', HostingProvider::Cpanel))
            ->chunkById(50, function ($sites) {
                foreach ($sites as $site) {
                    DeleteFiles::dispatch($site)->onQueue('low');
                }
            });
    }
}
