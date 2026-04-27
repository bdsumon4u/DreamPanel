<?php

namespace App\Models;

use App\Enums\SiteStatus;
use App\Services\HostingProviders\CloudPanelProvider;
use App\Services\HostingProviders\Contracts\HasSiteUser;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Site extends Model
{
    use BelongsToOrganization;
    use LogsActivity;
    use SoftDeletes;

    protected $hidden = [
        // 'email_password',
        // 'database_pass',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
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
        $provider = $this->hosting->provider();
        if ($provider instanceof HasSiteUser) {
            return $provider->getSiteUser($this);
        }

        return $this->hosting->username;
    }

    public function getDirectoryAttribute(string $value = ''): string
    {
        $provider = $this->hosting->provider();
        if (! $provider instanceof CloudPanelProvider) {
            return $value ?? $this->domain;
        }

        return '/home/'.$this->username.'/htdocs/'.($value ?? $this->domain);
    }

    public function getPrefixedDatabaseNameAttribute(): string
    {
        return $this->hosting->username.'_'.$this->database_name;
    }

    public function getPrefixedDatabaseUserAttribute(): string
    {
        return $this->hosting->username.'_'.$this->database_user;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('site');
        // Chain fluent methods for configuration options
    }
}
