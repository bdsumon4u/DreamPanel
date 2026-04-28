<?php

namespace App\Filament\Resources\Sites\Pages\Actions;

use App\Enums\SiteStatus;
use App\Jobs\DeleteDomain;
use App\Models\Hosting;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;

class DeleteSitesAction extends Action
{
    public static function make(?string $name = 'delete-sites'): static
    {
        return parent::make($name)
            ->label('Delete sites')
            ->color(Color::Red)
            ->schema([
                Select::make('hosting_id')
                    ->label('Hosting')
                    ->options(fn () => Hosting::query()->pluck('domain', 'id'))
                    ->searchable()
                    ->required()
                    ->live(),
                Select::make('sites')
                    ->label('Sites')
                    ->options(function (Get $get) {
                        return Site::where('hosting_id', $get('hosting_id'))->pluck('domain', 'id');
                    })
                    ->searchable()
                    ->multiple()
                    ->required(),
            ])
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Delete selected')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('delete-remaining', arguments: ['delete_remaining' => true])
                    ->label('Delete remaining')
                    ->color(Color::Yellow),
            ])
            ->action(function (array $data, array $arguments): void {
                $query = Site::where('hosting_id', $data['hosting_id']);

                if ($arguments['delete_remaining'] ?? false) {
                    $query->whereNotIn('id', $data['sites']);
                } else {
                    $query->whereIn('id', $data['sites']);
                }

                $query->chunkById(500, function ($sites): void {
                    $sites->each(function (Site $site) {
                        DeleteDomain::dispatch($site)->onQueue('low');

                        $site->update(['status' => SiteStatus::PENDING_DELETE]);
                    });
                });
            });
    }
}
