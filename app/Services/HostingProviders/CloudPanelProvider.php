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
        try {
            $this->runRootCommand($site->hosting, sprintf(
                'clpctl site:add:php --domainName=%s --phpVersion=8.4 --vhostTemplate=%s --siteUser=%s --siteUserPassword=%s',
                $this->escape($site->domain),
                $this->escape('Laravel 12'),
                $this->escape($this->getSiteUser($site)),
                $this->escape('Password123!')
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
    }

    private function runRootCommand(Hosting $hosting, string $command): void
    {
        if ($hosting->provider() !== HostingProvider::CloudPanel) {
            throw new UnsupportedHostingOperationException('CloudPanel CLI is available only for cloudpanel hostings.');
        }

        Log::info('Executing CloudPanel CLI command', [
            'hosting_id' => $hosting->id,
            'server_id' => $hosting->server_id,
            'command' => $command,
        ]);

        $process = Ssh::create((string) ($hosting->username ?: 'root'), $hosting->connectionIp())
            ->usePrivateKey(Storage::disk('local')->path('HOTASH'))
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
