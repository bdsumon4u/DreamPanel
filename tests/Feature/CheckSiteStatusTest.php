<?php

use App\Enums\HostingProvider;
use App\Enums\SiteStatus;
use App\Jobs\CheckSiteStatus;
use App\Models\Hosting;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createSiteForStatusCheck(array $siteAttributes = []): Site
{
    $suffix = fake()->unique()->numerify('#####');

    $hosting = Hosting::query()->create([
        'provider' => HostingProvider::Cpanel,
        'ip' => '127.0.0.1',
        'domain' => 'host-'.$suffix.'.test',
        'username' => 'user',
        'password' => 'secret',
        'token' => 'token',
    ]);

    return Site::query()->create(array_merge([
        'hosting_id' => $hosting->id,
        'name' => 'Test',
        'domain' => 'example-'.$suffix.'.test',
        'directory' => 'public_html',
        'email_username' => 'a@b.com',
        'email_password' => 'x',
        'database_name' => 'db',
        'database_user' => 'dbu',
        'database_pass' => 'dbp',
        'status' => SiteStatus::SITE_ACTIVE,
        'laravel_maintenance_mode' => false,
    ], $siteAttributes));
}

it('does not mark site as down when laravel maintenance mode is on and http is not ok', function () {
    $site = createSiteForStatusCheck([
        'laravel_maintenance_mode' => true,
    ]);

    Http::fake([
        '*' => Http::response('', 503),
    ]);

    (new CheckSiteStatus($site))->handle();

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::SITE_ACTIVE);
    expect($site->laravel_maintenance_mode)->toBeTrue();
});

it('clears laravel maintenance mode when site responds ok', function () {
    $site = createSiteForStatusCheck([
        'laravel_maintenance_mode' => true,
    ]);

    Http::fake([
        '*' => Http::response('', 200),
    ]);

    (new CheckSiteStatus($site))->handle();

    $site->refresh();

    expect($site->laravel_maintenance_mode)->toBeFalse();
    expect($site->status)->toBe(SiteStatus::SITE_ACTIVE);
});

it('marks site down when not in maintenance and http is not ok', function () {
    $site = createSiteForStatusCheck([
        'laravel_maintenance_mode' => false,
    ]);

    Http::fake([
        '*' => Http::response('', 503),
    ]);

    (new CheckSiteStatus($site))->handle();

    $site->refresh();

    expect($site->status)->toBe(SiteStatus::SITE_DOWN);
});
