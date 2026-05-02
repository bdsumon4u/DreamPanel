<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\BulkAction;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Collection;

class BulkBringMaintenanceSitesLiveAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk-bring-maintenance-sites-live';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Bring live'));
        $this->modalHeading(__('Bring selected sites live'));
        $this->modalDescription(__('Runs ./php artisan up on each selected site that is still flagged as in maintenance.'));
        $this->successNotificationTitle(__('Live mode queued'));
        $this->color(Color::Green);
        $this->icon('heroicon-o-check-circle');
        $this->requiresConfirmation();

        $this->action(static function (Collection $records): void {
            $records
                ->filter(static fn ($record): bool => $record instanceof Site && $record->laravel_maintenance_mode)
                ->each(static fn (Site $site) => RunRemoteArtisanMaintenance::dispatch($site, 'up')->onQueue('high'));
        });
    }
}
