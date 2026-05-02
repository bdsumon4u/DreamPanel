<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;

class SiteMaintenanceDownAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'maintenance-down';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Maintenance on'));
        $this->modalHeading(__('Put site in maintenance mode'));
        $this->modalDescription(__('Runs ./php artisan down on the remote app (non-interactive).'));
        $this->successNotificationTitle(__('Maintenance mode queued'));
        $this->color(Color::Orange);
        $this->icon('heroicon-o-no-symbol');
        $this->requiresConfirmation();

        $this->visible(fn (Model $record): bool => $record instanceof Site && $record->allowsRemoteArtisanMaintenance());

        $this->action(static function (Model $record): void {
            if (! $record instanceof Site) {
                return;
            }

            RunRemoteArtisanMaintenance::dispatch($record, 'down')->onQueue('high');
        });
    }
}
