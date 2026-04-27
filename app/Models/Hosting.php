<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HostingProvider;
use App\Exceptions\UnsupportedHostingOperationException;
use App\Services\HostingProviders\Contracts\NeedsSshAuthorization;
use App\Services\HostingProviders\HostingProviderResolver;
use App\Traits\BelongsToOrganization;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Hosting extends Model
{
    use BelongsToOrganization;

    public const DEFAULT_FTP_PORT = 21;

    public const DEFAULT_SSH_PORT = 22;

    public const DEFAULT_SITE_LIMIT = 1;

    protected $hidden = [
        // 'password',
        // 'token',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'server_id' => 'integer',
            'provider' => HostingProvider::class,
            'ip' => 'string',
            'password' => 'encrypted',
            'token' => 'encrypted',
            'site_limit' => 'integer',
            'ftp_port' => 'integer',
            'ssh_port' => 'integer',
        ];
    }

    public function domain(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->ip ?? $this->server?->ip,
        );
    }

    public function ip(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? $this->server?->ip,
        );
    }

    public function username(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ?? 'root',
        );
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function ftp(): Filesystem
    {
        return Storage::build([
            'driver' => 'ftp',
            'host' => $this->connectionIp(),
            'username' => $this->username,
            'password' => $this->password,
            // Optional but recommended
            'port' => $this->ftpPort(),
            'root' => env('FTP_ROOT', '/'),
            'passive' => true,
            'ssl' => false,
            'timeout' => 30,
        ]);
    }

    public function provider(): HostingProvider
    {
        return $this->getAttribute('provider') ?? HostingProvider::Cpanel;
    }

    public function connectionIp(): string
    {
        $ip = $this->ip ?: (string) $this->server?->ip;

        if ($ip === '') {
            throw new UnsupportedHostingOperationException('No connection IP configured for hosting endpoint.');
        }

        return $ip;
    }

    public function ftpPort(): int
    {
        return $this->ftp_port ?? (int) ($this->server?->ftp_port ?? self::DEFAULT_FTP_PORT);
    }

    public function sshPort(): int
    {
        return $this->ssh_port ?? (int) ($this->server?->ssh_port ?? self::DEFAULT_SSH_PORT);
    }

    public function copySshKey(): void
    {
        $provider = app(HostingProviderResolver::class)->resolve($this);

        if (! $provider instanceof NeedsSshAuthorization) {
            return;
        }

        $provider->authorizeSshKey($this);
    }
}
