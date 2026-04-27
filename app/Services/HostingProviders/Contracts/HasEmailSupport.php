<?php

declare(strict_types=1);

namespace App\Services\HostingProviders\Contracts;

use App\Models\Site;

interface HasEmailSupport
{
    public function createOrUpdateEmailAccount(Site $site): void;
}
