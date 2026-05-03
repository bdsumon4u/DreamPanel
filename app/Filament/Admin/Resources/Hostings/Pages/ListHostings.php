<?php

namespace App\Filament\Admin\Resources\Hostings\Pages;

use App\Filament\Admin\Resources\Hostings\HostingResource;
use App\Filament\Resources\Sites\Pages\Actions\SshPubKeyCommandAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHostings extends ListRecords
{
    protected static string $resource = HostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            SshPubKeyCommandAction::make(),
        ];
    }
}
