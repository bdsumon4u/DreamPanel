<?php

namespace App\Filament\Admin\Resources\Sites\Schemas;

use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\Schemas\SiteForm as BaseSiteForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SiteForm extends BaseSiteForm
{
    protected static function organizationField(): Component
    {
        return Select::make('organization_id')
            ->label('Team')
            ->relationship('organization', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set): void {
                $set('hosting_id', null);
                $set('parent_id', null);
                $set('hosting_domain', null);
                $set('limit', null);
            });
    }

    protected static function copyFromField(): Component
    {
        return parent::copyFromField()
            ->relationship(
                name: 'parent',
                titleAttribute: 'domain',
                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                    ->when(
                        $get('organization_id'),
                        fn (Builder $query, int $organizationId) => $query->where('organization_id', $organizationId),
                    ),
            )
            ->disabled(fn (Get $get) => ! $get('organization_id'));
    }

    protected static function hostingField(): Component
    {
        return parent::hostingField()
            ->relationship(
                name: 'hosting',
                titleAttribute: 'domain',
                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                    ->when(
                        $get('organization_id'),
                        fn (Builder $query, int $organizationId) => $query->where('organization_id', $organizationId),
                    )
                    ->withCount('sites'),
            );
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::sshSection(),
                Group::make([
                    self::organizationField()
                        ->columnSpanFull(),
                    self::copyFromField(),
                    self::siteNameField(),
                    self::hostingField()
                        ->columnSpanFull(),
                    self::domainField(),
                    self::directoryField(),
                    TextInput::make('service_id')
                        ->label('Service ID'),
                    Select::make('status')
                        ->options(SiteStatus::class)
                        ->searchable()
                        ->required(),
                ])
                    ->dense()
                    ->columns(2)
                    ->columnSpan(2),
                Group::make([
                    self::emailSection()
                        ->columns(2)
                        ->columnSpan(1),
                    self::databaseSection()
                        ->columns(3)
                        ->columnSpan(1),
                ])
                    ->dense()
                    ->columnSpan(3),
            ])
            ->columns(5);
    }
}
