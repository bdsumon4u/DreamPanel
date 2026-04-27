<?php

use App\Enums\HostingProvider;
use App\Jobs\CreateDatabaseAndUser;
use App\Jobs\CreateEmailAccount;
use App\Jobs\CreateNewDomain;
use App\Models\Hosting;
use App\Models\Site;
use App\Services\HostingProviders\CloudPanelProvider;
use App\Services\HostingProviders\Contracts\HasEmailSupport;
use App\Services\HostingProviders\Contracts\HostingProvider as HostingProviderContract;
use App\Services\HostingProviders\CpanelProvider;
use App\Services\HostingProviders\HostingProviderResolver;

it('resolves cpanel provider by default when server is missing', function () {
    $hosting = new Hosting([
        'ip' => '192.0.2.10',
        'ftp_port' => 2121,
        'ssh_port' => 2222,
    ]);

    $provider = app(HostingProviderResolver::class)->resolve($hosting);

    expect($provider)->toBeInstanceOf(CpanelProvider::class);
    expect($hosting->connectionIp())->toBe('192.0.2.10');
    expect($hosting->ftpPort())->toBe(2121);
    expect($hosting->sshPort())->toBe(2222);
});

it('resolves cloudpanel provider when hosting provider is cloudpanel', function () {
    $hosting = new Hosting([
        'provider' => HostingProvider::CloudPanel,
        'ip' => '198.51.100.25',
        'ftp_port' => 21,
        'ssh_port' => 22,
    ]);

    $provider = app(HostingProviderResolver::class)->resolve($hosting);

    expect($provider)->toBeInstanceOf(CloudPanelProvider::class);
    expect($hosting->connectionIp())->toBe('198.51.100.25');
    expect($hosting->ftpPort())->toBe(21);
    expect($hosting->sshPort())->toBe(22);
});

it('delegates create and database jobs to resolved provider', function () {
    $hosting = new Hosting;
    $site = new Site;
    $site->setRelation('hosting', $hosting);

    $provider = \Mockery::mock(HostingProviderContract::class);
    $provider->shouldReceive('createNewDomain')->once()->with($site);
    $provider->shouldReceive('createOrUpdateDatabaseAndUser')->once()->with($site);

    $resolver = \Mockery::mock(HostingProviderResolver::class);
    $resolver->shouldReceive('resolve')->times(2)->with($hosting)->andReturn($provider);

    app()->instance(HostingProviderResolver::class, $resolver);

    (new CreateNewDomain($site))->handle();
    (new CreateDatabaseAndUser($site))->handle();
});

it('delegates email job when provider supports email', function () {
    $hosting = new Hosting;
    $site = new Site;
    $site->setRelation('hosting', $hosting);

    $provider = \Mockery::mock(HostingProviderContract::class, HasEmailSupport::class);
    $provider->shouldReceive('createOrUpdateEmailAccount')->once()->with($site);

    $resolver = \Mockery::mock(HostingProviderResolver::class);
    $resolver->shouldReceive('resolve')->once()->with($hosting)->andReturn($provider);

    app()->instance(HostingProviderResolver::class, $resolver);

    (new CreateEmailAccount($site))->handle();
});
