<?php

namespace App\Filament\Exports;

use App\Models\Site;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SiteExporter extends Exporter
{
    protected static ?string $model = Site::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('domain'),
            ExportColumn::make('status'),
            ExportColumn::make('laravel_maintenance_mode')
                ->label('Laravel maintenance'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your site export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
