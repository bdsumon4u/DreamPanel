<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;

class SiteMaintenanceUpAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'maintenance-up';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Maintenance off'));
        $this->modalHeading(__('Bring site out of maintenance'));
        $this->modalDescription(__('Runs ./php artisan up on the remote app.'));
        $this->successNotificationTitle(__('Live mode queued'));
        $this->color(Color::Green);
        $this->icon('heroicon-o-check-circle');
        $this->requiresConfirmation();

        $this->visible(fn (Model $record): bool => $record instanceof Site && $record->allowsRemoteArtisanMaintenance());

        $this->action(static function (Model $record): void {
            if (! $record instanceof Site) {
                return;
            }

            RunRemoteArtisanMaintenance::dispatch($record, 'up')->onQueue('high');
        });
    }
}
