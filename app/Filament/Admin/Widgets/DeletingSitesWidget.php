<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Sites\SiteResource;
use App\Filament\Widgets\DeletingSitesWidget as BaseDeletingSitesWidget;
use Filament\Tables\Table;

class DeletingSitesWidget extends BaseDeletingSitesWidget
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->columns($table->getColumns())
            ->recordUrl(fn ($record) => SiteResource::getUrl('activities', ['record' => $record]))
            ->filters($table->getFilters());
    }
}
