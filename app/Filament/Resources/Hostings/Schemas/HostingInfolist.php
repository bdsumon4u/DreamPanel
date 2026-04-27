<?php

namespace App\Filament\Resources\Hostings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HostingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $schema->getRecord()->loadCount('sites');

        return $schema
            ->components([
                TextEntry::make('organization.name')
                    ->label('Organization'),
                TextEntry::make('server.name')
                    ->label('Server')
                    ->placeholder('Standalone'),
                TextEntry::make('ip')
                    ->label('Direct IP')
                    ->placeholder('-'),
                TextEntry::make('domain'),
                TextEntry::make('username'),
                TextEntry::make('site_limit')
                    ->numeric(),
                TextEntry::make('sites_count')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
