<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SiteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('parent.name')
                    ->label('Parent'),
                TextEntry::make('hosting.domain')
                    ->label('Hosting'),
                TextEntry::make('name'),
                TextEntry::make('domain'),
                TextEntry::make('directory'),
                TextEntry::make('email_username'),
                TextEntry::make('database_name'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('laravel_maintenance_mode')
                    ->label(__('Laravel maintenance'))
                    ->formatStateUsing(fn (?bool $state): string => $state ? __('Yes (artisan down)') : __('No (live)'))
                    ->badge()
                    ->color(fn (?bool $state): string => $state ? 'warning' : 'success'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
