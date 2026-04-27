<?php

declare(strict_types=1);

namespace App\Services\HostingProviders\Contracts;

use App\Models\Site;

interface HostingProvider
{
    public function createNewDomain(Site $site): void;

    public function createOrUpdateDatabaseAndUser(Site $site): void;

    public function deleteDomain(Site $site): void;
}
