<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SitesRenewingWithinWeekWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Sites renewing within 7 days';

    public static function canView(): bool
    {
        return static::renewingWithinWeekQuery()->exists();
    }

    /**
     * @return Builder<Site>
     */
    protected static function renewingWithinWeekQuery(): Builder
    {
        $today = now()->startOfDay()->toDateString();
        $weekEnd = now()->addDays(7)->endOfDay()->toDateString();

        return Site::query()
            ->whereNotNull('renew_date')
            ->whereDate('renew_date', '>=', $today)
            ->whereDate('renew_date', '<=', $weekEnd);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('60s')
            ->query(
                static::renewingWithinWeekQuery()
                    ->with(['hosting'])
                    ->orderBy('renew_date')
            )
            ->groups([
                Group::make('hosting.domain'),
            ])
            ->columns([
                TextColumn::make('hosting.domain')
                    ->label(__('Hosting'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('domain')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('renew_date')
                    ->label(__('Renew date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50]);
    }
}
