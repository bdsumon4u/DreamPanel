<?php

namespace App\Filament\Admin\Resources\Sites\Tables;

use App\Filament\Admin\Resources\Sites\SiteResource;
use App\Filament\Admin\Resources\Sites\Tables\Actions\BulkSiteUpdateAction;
use App\Filament\Exports\SiteExporter;
use App\Filament\Resources\Sites\Tables\SitesTable as BaseSitesTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Table;

class SitesTable extends BaseSitesTable
{
    public static function configure(Table $table): Table
    {
        $table = parent::configure($table);

        return $table->columns($table->getColumns())
            ->filters([
                ...$table->getFilters(),
                QueryBuilder::make()->constraints([
                    NumberConstraint::make('service_id')
                        ->label(__('Service ID'))
                        ->nullable(),
                ]),
            ])
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]))
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(SiteExporter::class),
                    BulkSiteUpdateAction::make(),
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
