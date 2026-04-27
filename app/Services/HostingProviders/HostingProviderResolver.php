<?php

declare(strict_types=1);

namespace App\Services\HostingProviders;

use App\Enums\HostingProvider;
use App\Models\Hosting;
use App\Services\HostingProviders\Contracts\HostingProvider as HostingProviderContract;

class HostingProviderResolver
{
    public function resolve(Hosting $hosting): HostingProviderContract
    {
        return match ($hosting->provider()) {
            HostingProvider::CloudPanel => app(CloudPanelProvider::class),
            HostingProvider::Cpanel => app(CpanelProvider::class),
        };
    }
}
