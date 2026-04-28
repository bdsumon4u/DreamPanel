<?php

namespace App\Models;

use App\Enums\HostingProvider;
use App\Enums\SiteStatus;
use App\Services\HostingProviders\Contracts\HasSiteUser;
use App\Services\HostingProviders\HostingProviderResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Site extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $hidden = [
        // 'email_password',
        // 'database_pass',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'hosting_id' => 'integer',
            'email_password' => 'encrypted',
            'database_pass' => 'encrypted',
            'status' => SiteStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'parent_id');
    }

    public function hosting(): BelongsTo
    {
        return $this->belongsTo(Hosting::class);
    }

    public function getUsernameAttribute(): string
    {
        $provider = app(HostingProviderResolver::class)->resolve($this->hosting);

        return $provider instanceof HasSiteUser ? $provider->getSiteUser($this) : $this->hosting->username;
    }

    public function getFullDirectoryAttribute(): string
    {
        if ($this->hosting->provider() !== HostingProvider::CloudPanel) {
            return $this->directory ?? $this->domain;
        }

        return '/home/'.$this->username.'/htdocs/'.($this->directory ?? $this->domain);
    }

    public function getPrefixedDatabaseNameAttribute(): string
    {
        return $this->hosting->username.'_'.$this->database_name;
    }

    public function getPrefixedDatabaseUserAttribute(): string
    {
        return $this->hosting->username.'_'.$this->database_user;
    }

    public function getEffectiveDatabaseNameAttribute(): string
    {
        return $this->hosting->provider() === HostingProvider::CloudPanel ? $this->database_name : $this->prefixed_database_name;
    }

    public function getEffectiveDatabaseUserAttribute(): string
    {
        return $this->hosting->provider() === HostingProvider::CloudPanel ? $this->database_user : $this->prefixed_database_user;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('site');
        // Chain fluent methods for configuration options
    }
}
