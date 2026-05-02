<?php

namespace App\Filament\Widgets;

use App\Enums\HostingProvider;
use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\DeleteFiles;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DeletingSitesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Sites Being Deleted';

    public static function canView(): bool
    {
        return static::deletingSitesQuery()->exists();
    }

    /**
     * @return Builder<Site>
     */
    protected static function deletingSitesQuery(): Builder
    {
        return Site::onlyTrashed()
            ->whereHas('hosting', fn ($query) => $query->where('provider', HostingProvider::Cpanel))
            ->where('status', SiteStatus::DELETING);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->query(
                static::deletingSitesQuery()->orderBy('updated_at', 'desc')
            )
            ->groups([
                Group::make('hosting.domain'),
            ])
            ->columns([
                TextColumn::make('hosting.domain')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('domain')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => SiteResource::getUrl('activities', ['record' => $record]))
            ->recordActions([
                Action::make('delete-files')
                    ->action(fn ($record) => DeleteFiles::dispatch($record)->onQueue('high')),
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
