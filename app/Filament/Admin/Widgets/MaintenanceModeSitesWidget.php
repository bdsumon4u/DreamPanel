<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Sites\SiteResource;
use App\Filament\Widgets\MaintenanceModeSitesWidget as BaseMaintenanceModeSitesWidget;
use Filament\Tables\Table;

class MaintenanceModeSitesWidget extends BaseMaintenanceModeSitesWidget
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->heading(__('Sites in maintenance mode'))
            ->description(__('Sites that are in maintenance mode are not accessible to the public.'))
            ->columns($table->getColumns())
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]))
            ->filters($table->getFilters());
    }
}
