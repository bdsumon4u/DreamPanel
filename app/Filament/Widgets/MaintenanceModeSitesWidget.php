<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\RunRemoteArtisanMaintenance;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceModeSitesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Sites in Laravel maintenance';

    public static function canView(): bool
    {
        return static::maintenanceModeQuery()->exists();
    }

    /**
     * @return Builder<Site>
     */
    protected static function maintenanceModeQuery(): Builder
    {
        return Site::query()->where('laravel_maintenance_mode', true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->query(
                static::maintenanceModeQuery()
                    ->with(['hosting'])
                    ->orderByDesc('id')
            )
            ->groups([
                Group::make('hosting.domain'),
            ])
            ->columns([
                TextColumn::make('hosting.domain')
                    ->label('Hosting')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('domain')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('bring-live')
                    ->label(__('Bring live'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Site $record) => RunRemoteArtisanMaintenance::dispatch($record, 'up')->onQueue('high')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkBringMaintenanceSitesLiveAction::make(),
                ]),
            ])
            ->filters([
                SelectFilter::make('hosting')
                    ->relationship('hosting', 'domain')
                    ->searchable(['domain', 'username'])
                    ->preload(),
            ])
            ->paginated([10, 25, 50]);
    }
}
