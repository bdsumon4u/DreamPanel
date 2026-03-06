<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Resources\Sites\Tables\Actions\ForceUpdateAction;
use App\Filament\Resources\Sites\Tables\Actions\SiteDeleteAction;
use App\Filament\Resources\Sites\Tables\Actions\SiteRedeployAction;
use App\Filament\Resources\Sites\Tables\Actions\SiteUpdateAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->defaultSort('id', 'desc')
            ->groups([
                Group::make('hosting.domain'),
            ])
            ->columns([
                TextColumn::make('parent.name')
                    ->sortable()
                    ->searchable()
                    ->description(fn (Model $record): ?string => $record->parent?->domain),
                TextColumn::make('hosting.domain')
                    ->sortable()
                    ->searchable()
                    ->description(fn (Model $record): string => $record->hosting->username),
                TextColumn::make('domain')
                    ->url(fn ($record) => 'http://'.$record->domain)
                    ->label('Domain')
                    ->openUrlInNewTab()
                    ->iconPosition('after')
                    ->icon('heroicon-o-link')
                    ->sortable()
                    ->searchable()
                    ->description(fn (Model $record): string => $record->directory),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->searchable()
                    ->queries(
                        true: fn ($query) => $query->onlyTrashed(),
                        false: fn ($query) => $query->withoutTrashed(),
                        blank: fn ($query) => $query->withTrashed(),
                    )
                    ->trueLabel('Only Trashed')
                    ->falseLabel('Without Trashed')
                    ->label('With Trashed')
                    ->placeholder('With Trashed'),
                SelectFilter::make('hosting')
                    ->relationship('hosting', 'domain')
                    ->searchable(['domain', 'username'])
                    ->preload(),
                SelectFilter::make('status')
                    ->options(SiteStatus::class)
                    ->searchable(),
            ])
            ->recordUrl(fn ($record) => SiteResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    SiteRedeployAction::make(),
                    SiteUpdateAction::make(),
                    ForceUpdateAction::make(),
                ])
                    ->color(Color::Yellow),
                EditAction::make(),
                SiteDeleteAction::make(),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
