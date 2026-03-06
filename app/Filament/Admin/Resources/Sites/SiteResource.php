<?php

namespace App\Filament\Admin\Resources\Sites;

use App\Filament\Admin\Resources\Sites\Pages\CreateSite;
use App\Filament\Admin\Resources\Sites\Pages\EditSite;
use App\Filament\Admin\Resources\Sites\Pages\ListActivities;
use App\Filament\Admin\Resources\Sites\Pages\ListSites;
use App\Filament\Admin\Resources\Sites\Pages\MultiSite;
use App\Filament\Admin\Resources\Sites\Pages\ViewSite;
use App\Filament\Admin\Resources\Sites\Schemas\SiteForm;
use App\Filament\Admin\Resources\Sites\Schemas\SiteInfolist;
use App\Filament\Admin\Resources\Sites\Tables\SitesTable;
use App\Models\Site;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'domain';

    public static function form(Schema $schema): Schema
    {
        return SiteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SiteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'create' => CreateSite::route('/create'),
            'multi' => MultiSite::route('/multi'),
            'activities' => ListActivities::route('/{record}/activities'),
            'view' => ViewSite::route('/{record}'),
            'edit' => EditSite::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
