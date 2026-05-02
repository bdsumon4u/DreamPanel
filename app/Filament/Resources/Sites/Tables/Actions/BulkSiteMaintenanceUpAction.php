<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\BulkAction;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Collection;

class BulkSiteMaintenanceUpAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk-maintenance-up';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Maintenance off'));
        $this->modalHeading(__('Bring selected sites out of maintenance'));
        $this->modalDescription(__('Runs ./php artisan up on each active site.'));
        $this->successNotificationTitle(__('Live mode queued'));
        $this->color(Color::Green);
        $this->icon('heroicon-o-check-circle');
        $this->requiresConfirmation();

        $this->action(static function (Collection $records): void {
            $records
                ->filter(static fn ($record): bool => $record instanceof Site && $record->allowsRemoteArtisanMaintenance())
                ->each(static fn (Site $site) => RunRemoteArtisanMaintenance::dispatch($site, 'up')->onQueue('high'));
        });
    }
}
