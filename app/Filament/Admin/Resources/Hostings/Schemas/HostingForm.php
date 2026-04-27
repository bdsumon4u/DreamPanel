<?php

namespace App\Filament\Admin\Resources\Hostings\Schemas;

use App\Enums\HostingProvider;
use App\Models\Hosting;
use App\Models\Server;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class HostingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('provider')
                    ->options(HostingProvider::class)
                    ->default(HostingProvider::Cpanel)
                    ->required()
                    ->inline()
                    ->live()
                    ->columnSpan(fn (Get $get): int => self::resolvedProvider($get) === HostingProvider::Cpanel->value ? 2 : 1)
                    ->afterStateUpdated(function (Set $set, Get $get, $livewire): void {
                        $livewire->form->fill([
                            'provider' => $get('provider'),
                            'ftp_port' => Hosting::DEFAULT_FTP_PORT,
                            'ssh_port' => Hosting::DEFAULT_SSH_PORT,
                            'site_limit' => Hosting::DEFAULT_SITE_LIMIT,
                        ]);
                    }),
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('server_id')
                    ->relationship('server', 'name')
                    ->hint(str('Optional for standalone cPanel accounts.')->inlineMarkdown()->toHtmlString())
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::Cpanel->value)
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        if (! $server = Server::find($get('server_id'))) {
                            return;
                        }

                        $set('ip', $server->ip);
                        $set('ftp_port', $server->ftp_port);
                        $set('ssh_port', $server->ssh_port);
                    }),
                TextInput::make('ip')
                    ->label('Direct IP')
                    ->required(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::CloudPanel->value),
                TextInput::make('domain')
                    ->required()
                    ->visible(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::Cpanel->value),
                TextInput::make('username')
                    ->required()
                    ->minLength(2)
                    ->visible(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::Cpanel->value),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->visible(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::Cpanel->value),
                TextInput::make('token')
                    ->required()
                    ->visible(fn (Get $get): bool => self::resolvedProvider($get) === HostingProvider::Cpanel->value),
                TextInput::make('ftp_port')
                    ->numeric()
                    ->default(Hosting::DEFAULT_FTP_PORT)
                    ->readOnly(fn (Get $get): bool => filled($get('server_id'))),
                TextInput::make('ssh_port')
                    ->numeric()
                    ->default(Hosting::DEFAULT_SSH_PORT)
                    ->readOnly(fn (Get $get): bool => filled($get('server_id'))),
                TextInput::make('site_limit')
                    ->required()
                    ->numeric()
                    ->default(Hosting::DEFAULT_SITE_LIMIT),
            ])
            ->columns(3);
    }

    private static function resolvedProvider(Get $get): string
    {
        $provider = $get('provider');

        if ($provider instanceof HostingProvider) {
            return $provider->value;
        }

        return (string) ($provider ?: HostingProvider::Cpanel->value);
    }
}
