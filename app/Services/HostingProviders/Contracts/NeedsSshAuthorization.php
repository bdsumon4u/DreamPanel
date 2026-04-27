<?php

declare(strict_types=1);

namespace App\Services\HostingProviders\Contracts;

use App\Models\Hosting;

interface NeedsSshAuthorization
{
    public function authorizeSshKey(Hosting $hosting): void;
}
