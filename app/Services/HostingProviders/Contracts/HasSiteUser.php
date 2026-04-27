<?php

namespace App\Services\HostingProviders\Contracts;

use App\Models\Site;

interface HasSiteUser
{
    public function getSiteUser(Site $site): string;
}
