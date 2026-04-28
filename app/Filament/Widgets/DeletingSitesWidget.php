<?php

namespace App\Filament\Widgets;

use App\Enums\HostingProvider;
use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\SiteResource;
use App\Jobs\DeleteFiles;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DeletingSitesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Sites Being Deleted';

    public function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->query(
                Site::onlyTrashed()
                    ->when(Filament::getTenant(), fn ($query) => $query->whereBelongsTo(Filament::getTenant()))
                    ->whereHas('hosting', fn ($query) => $query->where('provider', HostingProvider::Cpanel))
                    ->where('status', SiteStatus::DELETING)
                    ->orderBy('updated_at', 'desc')
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
                SelectFilter::make('organization')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('hosting')
                    ->relationship('hosting', 'domain')
                    ->searchable(['domain', 'username'])
                    ->preload(),
            ])
            ->paginated([10, 25, 50]);
    }
}
