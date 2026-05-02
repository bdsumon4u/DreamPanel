<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\BulkAction;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Collection;

class BulkSiteMaintenanceDownAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk-maintenance-down';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Maintenance on'));
        $this->modalHeading(__('Put selected sites in maintenance mode'));
        $this->modalDescription(__('Runs ./php artisan down on each active site (non-interactive).'));
        $this->successNotificationTitle(__('Maintenance mode queued'));
        $this->color(Color::Orange);
        $this->icon('heroicon-o-no-symbol');
        $this->requiresConfirmation();

        $this->action(static function (Collection $records): void {
            $records
                ->filter(static fn ($record): bool => $record instanceof Site && $record->allowsRemoteArtisanMaintenance())
                ->each(static fn (Site $site) => RunRemoteArtisanMaintenance::dispatch($site, 'down')->onQueue('high'));
        });
    }
}
