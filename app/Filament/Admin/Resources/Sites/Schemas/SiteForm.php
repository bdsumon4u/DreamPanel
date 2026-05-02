<?php

namespace App\Filament\Admin\Resources\Sites\Schemas;

use App\Enums\SiteStatus;
use App\Filament\Resources\Sites\Schemas\SiteForm as BaseSiteForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class SiteForm extends BaseSiteForm
{
    protected static function copyFromField(): Component
    {
        return parent::copyFromField();
    }

    protected static function hostingField(): Component
    {
        return parent::hostingField()
            ->relationship('hosting', 'domain', fn ($query) => $query->withCount('sites'));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::sshSection(),
                Group::make([
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
                        ->collapsed(false)
                        ->columns(2)
                        ->columnSpanFull(),
                    self::siteUserField(),
                    self::sitePasswordField(),
                    self::databaseSection()
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                    ->columns(2)
                    ->dense()
                    ->columnSpan(3),
            ])
            ->columns(5);
    }
}
