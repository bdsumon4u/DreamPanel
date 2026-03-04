<?php

namespace App\Filament\Admin\Resources\Sites\Tables\Actions;

use App\Jobs\UpdateSite;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

class BulkSiteUpdateAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk-update-site';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Update Sites'));
        $this->successNotificationTitle(__('Updating sites'));
        $this->icon('heroicon-o-arrow-path');

        $this->action(static function (Collection $records): void {
            $records->each(static fn ($record) => UpdateSite::dispatch($record)->onQueue('high'));
        });
    }
}
