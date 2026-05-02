<?php

declare(strict_types=1);

namespace App\Services\HostingProviders;

use App\Enums\HostingProvider;
use App\Exceptions\UnsupportedHostingOperationException;
use App\Models\Hosting;
use App\Models\Site;
use App\Services\HostingProviders\Contracts\HasSiteUser;
use App\Services\HostingProviders\Contracts\HostingProvider as HostingProviderContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Ssh\Ssh;

class CloudPanelProvider implements HasSiteUser, HostingProviderContract
{
    public function createNewDomain(Site $site): void
    {
        $this->createNewDomainAttempt($site, allowOrphanRecovery: true);
    }

    private function createNewDomainAttempt(Site $site, bool $allowOrphanRecovery): void
    {
        try {
            $this->runRootCommand($site->hosting, sprintf(
                'clpctl site:add:php --domainName=%s --phpVersion=8.4 --vhostTemplate=%s --siteUser=%s --siteUserPassword=%s',
                $this->escape($site->domain),
                $this->escape('Laravel 12'),
                $this->escape($this->getSiteUser($site)),
                $this->escape($this->getSitePassword($site))
            ));
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'domainName: This value already exists.')) {
                Log::info('CloudPanel domain already exists. Skipping create command.', [
                    'site_id' => $site->id,
                    'domain' => $site->domain,
                    'hosting_id' => $site->hosting_id,
                ]);

                return;
            }

            if (str_contains($exception->getMessage(), 'siteUser: This value already exists.')) {
                if ($this->cloudPanelSiteHtdocsDirectoryExists($site)) {
                    Log::info('CloudPanel site user already exists and htdocs path is present. Skipping create command.', [
                        'site_id' => $site->id,
                        'domain' => $site->domain,
                        'hosting_id' => $site->hosting_id,
                    ]);

                    return;
                }

                if (! $allowOrphanRecovery) {
                    info('CloudPanel domain creation failed: '.$exception->getMessage());
                    throw $exception;
                }

                Log::warning('CloudPanel site user exists but site directory is missing; cleaning orphan user and retrying site:add.', [
                    'site_id' => $site->id,
                    'domain' => $site->domain,
                    'site_user' => $this->getSiteUser($site),
                    'hosting_id' => $site->hosting_id,
                ]);

                $this->cleanupOrphanCloudPanelSiteAndUser($site);
                $this->createNewDomainAttempt($site, allowOrphanRecovery: false);

                return;
            }

            info('CloudPanel domain creation failed: '.$exception->getMessage());
            throw $exception;
        }
    }

    public function createOrUpdateDatabaseAndUser(Site $site): void
    {
        try {
            $this->runRootCommand($site->hosting, sprintf(
                'clpctl db:add --domainName=%s --databaseName=%s --databaseUserName=%s --databaseUserPassword=%s',
                $this->escape($site->domain),
                $this->escape($site->database_name),
                $this->escape($site->database_user),
                $this->escape($site->database_pass)
            ));
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();

            if (
                str_contains($message, 'databaseName: This value already exists.')
                || str_contains($message, 'databaseUserName: This value already exists.')
            ) {
                Log::info('CloudPanel database or user already exists. Skipping create command.', [
                    'site_id' => $site->id,
                    'domain' => $site->domain,
                    'database_name' => $site->database_name,
                    'database_user' => $site->database_user,
                    'hosting_id' => $site->hosting_id,
                ]);

                return;
            }

            throw $exception;
        }
    }

    public function deleteDomain(Site $site): void
    {
        $this->runRootCommand($site->hosting, sprintf(
            'clpctl site:delete --domainName=%s --force',
            $this->escape($site->domain)
        ));

        $siteUser = $this->getSiteUser($site);
        $hostingUsername = (string) $site->hosting->username;

        if ($siteUser === '' || $siteUser === $hostingUsername) {
            return;
        }

        try {
            $this->runRootCommand($site->hosting, sprintf(
                'clpctl user:delete --userName=%s',
                $this->escape($siteUser)
            ));
        } catch (\RuntimeException $exception) {
            if ($this->isBenignCloudPanelUserDeleteFailure($exception->getMessage())) {
                Log::info('CloudPanel site user already removed. Skipping user delete command.', [
                    'site_id' => $site->id,
                    'site_user' => $siteUser,
                    'hosting_id' => $site->hosting_id,
                ]);
            } else {
                throw $exception;
            }
        }

        $this->removeLinuxSystemSiteUserIfPresent($site->hosting, $siteUser);
    }

    /**
     * CloudPanel stores each site under /home/{siteUser}/htdocs/{domainName}.
     * If the Linux user exists but this path is missing, site:add failed partway and db:add will error with "DomainName does not exist".
     */
    private function cloudPanelSiteHtdocsDirectoryExists(Site $site): bool
    {
        $siteUser = $this->getSiteUser($site);
        $path = '/home/'.$siteUser.'/htdocs/'.$site->domain;

        return $this->remoteDirectoryExists($site->hosting, $path);
    }

    private function remoteDirectoryExists(Hosting $hosting, string $absolutePath): bool
    {
        if ($hosting->provider() !== HostingProvider::CloudPanel) {
            throw new UnsupportedHostingOperationException('CloudPanel CLI is available only for cloudpanel hostings.');
        }

        $process = Ssh::create((string) ($hosting->username ?: 'root'), $hosting->connectionIp())
            ->usePrivateKey(Storage::disk('local')->path('HotashTech'))
            ->disablePasswordAuthentication()
            ->disableStrictHostKeyChecking()
            ->usePort($hosting->sshPort())
            ->setTimeout(60)
            ->execute(['test -d '.escapeshellarg($absolutePath)]);

        return $process->isSuccessful();
    }

    private function cleanupOrphanCloudPanelSiteAndUser(Site $site): void
    {
        $hosting = $site->hosting;
        $siteUser = $this->getSiteUser($site);
        $hostingUsername = (string) $site->hosting->username;

        try {
            $this->runRootCommand($hosting, sprintf(
                'clpctl site:delete --domainName=%s --force',
                $this->escape($site->domain)
            ));
        } catch (\RuntimeException) {
            Log::debug('CloudPanel site:delete during orphan cleanup failed (domain may not exist).', [
                'site_id' => $site->id,
                'domain' => $site->domain,
            ]);
        }

        if ($siteUser === '' || $siteUser === $hostingUsername) {
            return;
        }

        try {
            $this->runRootCommand($hosting, sprintf(
                'clpctl user:delete --userName=%s',
                $this->escape($siteUser)
            ));
        } catch (\RuntimeException $exception) {
            if (! $this->isBenignCloudPanelUserDeleteFailure($exception->getMessage())) {
                throw $exception;
            }

            Log::debug('CloudPanel user:delete during orphan cleanup skipped (no matching panel user).', [
                'site_id' => $site->id,
                'site_user' => $siteUser,
            ]);
        }

        $this->removeLinuxSystemSiteUserIfPresent($hosting, $siteUser);
    }

    /**
     * Site provisioning creates a Linux account for --siteUser. clpctl user:delete removes CloudPanel
     * admin users and often does not remove that system account, which keeps site:add from reusing the name.
     */
    private function removeLinuxSystemSiteUserIfPresent(Hosting $hosting, string $siteUser): void
    {
        if (! $this->mayRemoveLinuxSiteUserAccount($hosting, $siteUser)) {
            return;
        }

        $userArg = escapeshellarg($siteUser);
        $command = sprintf(
            'if id -u %1$s >/dev/null 2>&1; then userdel -r %1$s || userdel -rf %1$s || exit 1; fi',
            $userArg
        );

        $this->runRootCommand($hosting, $command, logLabel: 'Executing Linux userdel for CloudPanel site user');
    }

    private function mayRemoveLinuxSiteUserAccount(Hosting $hosting, string $siteUser): bool
    {
        if ($siteUser === '') {
            return false;
        }

        $hostingUsername = (string) $hosting->username;
        if ($siteUser === $hostingUsername) {
            return false;
        }

        $reserved = ['root', 'nobody', 'www-data', 'debian', 'ubuntu', 'admin', 'mysql', 'postgres', 'mail', 'clamav'];
        if (in_array($siteUser, $reserved, true)) {
            return false;
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,30}$/', $siteUser) !== 1) {
            Log::warning('Refusing Linux userdel: site username has unexpected shape.', [
                'site_user' => $siteUser,
                'hosting_id' => $hosting->id,
            ]);

            return false;
        }

        return true;
    }

    private function isBenignCloudPanelUserDeleteFailure(string $message): bool
    {
        return str_contains($message, 'User not found')
            || str_contains($message, 'user not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'This value does not exist');
    }

    private function runRootCommand(Hosting $hosting, string $command, string $logLabel = 'Executing CloudPanel CLI command'): void
    {
        if ($hosting->provider() !== HostingProvider::CloudPanel) {
            throw new UnsupportedHostingOperationException('CloudPanel CLI is available only for cloudpanel hostings.');
        }

        Log::info($logLabel, [
            'hosting_id' => $hosting->id,
            'server_id' => $hosting->server_id,
            'command' => $command,
        ]);

        $process = Ssh::create((string) ($hosting->username ?: 'root'), $hosting->connectionIp())
            ->usePrivateKey(Storage::disk('local')->path('HotashTech'))
            ->disablePasswordAuthentication()
            ->disableStrictHostKeyChecking()
            ->usePort($hosting->sshPort())
            ->setTimeout(120)
            ->execute([$command]);

        if (! $process->isSuccessful()) {
            $stderr = $this->sanitizeSshErrorOutput($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $failureReason = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'No output returned.');

            throw new \RuntimeException('CloudPanel CLI command failed: '.$failureReason);
        }

        Log::info('CloudPanel CLI command completed', [
            'hosting_id' => $hosting->id,
            'server_id' => $hosting->server_id,
            'output' => trim($process->getOutput()),
        ]);
    }

    private function escape(string $value): string
    {
        return escapeshellarg($value);
    }

    public function getSiteUser(Site $site): string
    {
        if (filled($site->site_user)) {
            return (string) $site->site_user;
        }

        $maxLength = 24;
        $suffix = dechex((int) $site->id);
        $suffix = $suffix !== '' ? $suffix : substr(md5($site->domain), 0, 6);
        $suffix = Str::lower(preg_replace('/[^a-z0-9]/', '', $suffix) ?? '');

        $base = Str::lower($site->domain);
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?? '';

        if ($base === '' || ctype_digit($base[0])) {
            $base = 'site'.$base;
        }

        $availableBaseLength = max(1, $maxLength - strlen($suffix));
        $base = substr($base, 0, $availableBaseLength);
        $username = substr($base.$suffix, 0, $maxLength);

        if ($username === '' || ctype_digit($username[0])) {
            $username = 's'.substr($username, 0, $maxLength - 1);
        }

        return $username;
    }

    private function getSitePassword(Site $site): string
    {
        return filled($site->site_password) ? (string) $site->site_password : 'Password123!';
    }

    private function sanitizeSshErrorOutput(string $errorOutput): string
    {
        $lines = preg_split('/\R/', $errorOutput) ?: [];

        $filteredLines = array_filter($lines, static function (string $line): bool {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                return false;
            }

            return ! str_contains($trimmedLine, 'Permanently added')
                || ! str_contains($trimmedLine, 'to the list of known hosts.');
        });

        return trim(implode(PHP_EOL, $filteredLines));
    }
}
