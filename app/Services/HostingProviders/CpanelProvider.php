<?php

declare(strict_types=1);

namespace App\Services\HostingProviders;

use App\Enums\SiteStatus;
use App\Models\Hosting;
use App\Models\Site;
use App\Services\HostingProviders\Contracts\HasEmailSupport;
use App\Services\HostingProviders\Contracts\HostingProvider;
use App\Services\HostingProviders\Contracts\NeedsSshAuthorization;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CpanelProvider implements HasEmailSupport, HostingProvider, NeedsSshAuthorization
{
    public function createNewDomain(Site $site): void
    {
        if ($site->directory === 'public_html') {
            return;
        }

        $data = $this->cpanelApiCall($site->hosting, 'AddonDomain', 'addaddondomain', [
            'dir' => $site->directory,
            'newdomain' => $site->domain,
            'subdomain' => Str::beforeLast($site->domain, '.'),
        ], 'cpanelresult');

        if (array_key_exists('error', $data) && ! str($data['error'])->contains('already exists')) {
            $site->update(['status' => SiteStatus::DEPLOY_FAILED]);
            throw new \Exception($data['error']);
        }
    }

    public function createOrUpdateEmailAccount(Site $site): void
    {
        $data = $this->cpanelApiCall($site->hosting, 'Email', 'addpop', [
            'domain' => $site->domain,
            'email' => $site->email_username,
            'password' => $site->email_password,
        ], 'cpanelresult');

        if (array_key_exists('error', $data)) {
            throw_unless(Str::endsWith($data['error'], 'already exists!'), $data['error']);

            $data = $this->cpanelApiCall($site->hosting, 'Email', 'passwdpop', [
                'domain' => $site->domain,
                'email' => $site->email_username,
                'password' => $site->email_password,
            ], 'cpanelresult');
        }

        if (array_key_exists('error', $data)) {
            $site->update(['status' => SiteStatus::DEPLOY_FAILED]);
            throw new \Exception($data['error']);
        }
    }

    public function createOrUpdateDatabaseAndUser(Site $site): void
    {
        $data = $this->cpanelApiCall($site->hosting, 'MysqlFE', 'createdb', [
            'db' => $site->prefixed_database_name,
        ], 'cpanelresult');

        if (array_key_exists('error', $data)) {
            throw_unless(Str::contains($data['error'], 'already exists.'), $data['error']);
        }

        $data = $this->cpanelApiCall($site->hosting, 'MysqlFE', 'createdbuser', [
            'dbuser' => $site->prefixed_database_user,
            'password' => $site->database_pass,
        ], 'cpanelresult');

        if (array_key_exists('error', $data)) {
            throw_unless(Str::contains($data['error'], 'already exists.'), $data['error']);

            $data = $this->cpanelApiCall($site->hosting, 'MysqlFE', 'changedbuserpassword', [
                'dbuser' => $site->prefixed_database_user,
                'password' => $site->database_pass,
            ], 'cpanelresult');

            if (array_key_exists('error', $data)) {
                $site->update(['status' => SiteStatus::DEPLOY_FAILED]);
                throw new \Exception($data['error']);
            }
        }

        $data = $this->cpanelApiCall($site->hosting, 'MysqlFE', 'setdbuserprivileges', [
            'db' => $site->prefixed_database_name,
            'dbuser' => $site->prefixed_database_user,
            'privileges' => 'ALL PRIVILEGES',
        ], 'cpanelresult');

        if (array_key_exists('error', $data)) {
            $site->update(['status' => SiteStatus::DEPLOY_FAILED]);
            throw new \Exception('Failed to set privileges on database');
        }
    }

    public function deleteDomain(Site $site): void
    {
        $subdomain = Str::of($site->domain)
            ->beforeLast('.')
            ->append('.')
            ->append($site->hosting->domain);

        $data = $this->cpanelApiCall($site->hosting, 'AddonDomain', 'deladdondomain', [
            'domain' => $site->domain,
            'subdomain' => $subdomain,
        ], 'cpanelresult');

        if (array_key_exists('error', $data) && ! Str::contains($data['error'], 'does not correspond to')) {
            throw new \Exception('Failed to delete site from hosting server: '.$data['error']);
        }
    }

    public function authorizeSshKey(Hosting $hosting): void
    {
        try {
            $ftp = $hosting->ftp();
            $key = Storage::disk('local')->get('HotashTech');
            $publicKey = Storage::disk('local')->get('HotashTech.pub');

            $ftp->put('.ssh/HotashTech', $key, 'private');
            $ftp->put('.ssh/HotashTech.pub', $publicKey, 'private');

            Log::info('Importing SSH key for '.$hosting->domain);
            $importResponse = $this->cpanelApiCall($hosting, 'SSH', 'importkey', [
                'name' => 'HotashTech',
                'key' => $publicKey,
                'type' => 'public',
            ], 'cpanelresult');

            if (array_key_exists('error', $importResponse)) {
                Log::error('Failed to import SSH key: '.$importResponse['error']);
            }

            Log::info('Authorizing SSH key for '.$hosting->domain);
            $authorizeResponse = $this->cpanelApiCall($hosting, 'SSH', 'authkey', [
                'key' => 'HotashTech',
                'action' => 'authorize',
            ], 'cpanelresult');

            if (array_key_exists('error', $authorizeResponse)) {
                Log::error('Failed to authorize SSH key: '.$authorizeResponse['error']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to authorize SSH key: '.$e->getMessage());
        }
    }

    public function cpanelApiCall(Hosting $hosting, string $module, string $action, array $params = [], ?string $key = null): array
    {
        $endpoint = "https://{$hosting->connectionIp()}:2083/json-api/cpanel";

        $response = Http::withHeader('Authorization', "cpanel {$hosting->username}:{$hosting->token}")
            ->acceptJson()
            ->withoutVerifying()
            ->throw()
            ->get($endpoint, [
                'api.version' => 1,
                'cpanel_jsonapi_func' => $action,
                'cpanel_jsonapi_user' => $hosting->username,
                'cpanel_jsonapi_module' => $module,
                'cpanel_jsonapi_apiversion' => 2,
            ] + $params);

        return optional($response)->json($key, []);
    }
}
