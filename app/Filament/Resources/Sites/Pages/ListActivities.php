<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\SiteResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities as ListActivityLog;

class ListActivities extends ListActivityLog
{
    protected static string $resource = SiteResource::class;
}
