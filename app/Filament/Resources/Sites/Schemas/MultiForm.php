<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class MultiForm extends SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                self::sshSection(),
                self::copyFromField(),
                self::hostingField(),
                TextInput::make('email_password')
                    ->label('Email Password')
                    ->required()
                    ->default('Password123!')
                    ->visible(fn (Get $get): bool => ! self::isCloudPanelHosting($get))
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) {
                        foreach ($get('sites') ?? [] as $index => $site) {
                            $set("sites.{$index}.email_password", $state);
                        }
                    }),
                TextInput::make('database_pass')
                    ->label('Database Password')
                    ->required()
                    ->default('Password123!')
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) {
                        foreach ($get('sites') ?? [] as $index => $site) {
                            $set("sites.{$index}.database_pass", $state);
                        }
                    }),
                TextInput::make('site_password')
                    ->label('Site Password')
                    ->password()
                    ->revealable()
                    ->default('Password123!')
                    ->visible(fn (Get $get): bool => self::isCloudPanelHosting($get))
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state) {
                        foreach ($get('sites') ?? [] as $index => $site) {
                            $set("sites.{$index}.site_password", $state);
                        }
                    }),
                Repeater::make('sites')
                    ->label('Sites')
                    ->helperText(function (Get $get) {
                        if ($get('hosting_id')) {
                            return 'You can create up to '.$get('limit').' site(s) on the selected hosting.';
                        }

                        return 'Please select a hosting to know how many sites you can create.';
                    })
                    ->schema(self::siteForm())
                    ->minItems(1)
                    ->maxItems(fn (Get $get) => $get('limit') ?? 0)
                    ->columns(1)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ])
            ->columns(4);
    }

    public static function siteForm(): array
    {
        return [
            Grid::make(5)
                ->schema([
                    Group::make([
                        self::domainField('../../'),
                        self::directoryField('../../'),
                        self::siteUserField('../../'),
                        self::sitePasswordField('../../'),
                        self::renewDateField('../../')
                            ->columnSpanFull(),
                        self::emailSection('../../')
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                        ->dense()
                        ->columns(2)
                        ->columnSpan(2),
                    self::databaseSection()
                        ->columns(3)
                        ->columnSpan(3),
                ]),
        ];
    }
}
