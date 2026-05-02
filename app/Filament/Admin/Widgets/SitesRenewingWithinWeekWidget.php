<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Sites\SiteResource;
use App\Filament\Widgets\SitesRenewingWithinWeekWidget as BaseSitesRenewingWithinWeekWidget;
use Filament\Tables\Table;

class SitesRenewingWithinWeekWidget extends BaseSitesRenewingWithinWeekWidget
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->columns($table->getColumns())
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]));
    }
}
