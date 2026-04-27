<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HostingProvider: string implements HasLabel
{
    case Cpanel = 'cpanel';
    case CloudPanel = 'cloudpanel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cpanel => 'cPanel',
            self::CloudPanel => 'CloudPanel',
        };
    }
}
