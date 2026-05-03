<?php

namespace App\Filament\Admin\Resources\Hostings\Pages;

use App\Filament\Admin\Resources\Hostings\HostingResource;
use App\Filament\Resources\Sites\Pages\Actions\SshPubKeyCommandAction;
use Filament\Resources\Pages\CreateRecord;

class CreateHosting extends CreateRecord
{
    protected static string $resource = HostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SshPubKeyCommandAction::make(),
        ];
    }
}
