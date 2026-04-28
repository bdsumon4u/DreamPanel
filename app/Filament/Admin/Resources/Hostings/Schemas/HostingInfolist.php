<?php

namespace App\Filament\Admin\Resources\Hostings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HostingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('server.name')
                    ->label('Server')
                    ->placeholder('Standalone'),
                TextEntry::make('ip')
                    ->label('Direct IP')
                    ->placeholder('-'),
                TextEntry::make('domain'),
                TextEntry::make('site_limit')
                    ->numeric(),
                TextEntry::make('username'),
                TextEntry::make('password')
                    ->placeholder('-'),
                TextEntry::make('token')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('ftp_port')
                    ->numeric(),
                TextEntry::make('ssh_port')
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
