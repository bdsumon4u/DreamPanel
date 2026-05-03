<?php

namespace App\Filament\Resources\Sites\Pages\Actions;

use Filament\Actions\Action;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

class SshPubKeyCommandAction extends Action
{
    public static function make(?string $name = 'copy-ssh-pub-key'): static
    {
        $publicKey = Storage::drive('local')->get('HotashTech.pub');

        if (! $publicKey) {
            return parent::make($name)
                ->label('SSH Public Key Command')
                ->color(Color::Lime)
                ->icon('heroicon-o-clipboard-document')
                ->disabled()
                ->tooltip('SSH Public Key Not Found');
        }

        // Escape the public key for JavaScript
        $escapedKey = str_replace(['`', '\\', '$', "\n", "\r"], ['\\`', '\\\\', '\\$', '\\n', '\\r'], $publicKey);

        $command = "install -d -m 700 ~/.ssh && echo '".$escapedKey."' > ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys";

        return parent::make($name)
            ->label('Copy SSH Public Key Command')
            ->color(Color::Slate)
            ->icon('heroicon-o-command-line')
            ->extraAttributes([
                'x-data' => '{ command: `'.$command.'` }',
                'x-on:click.prevent' => <<<'JS'
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(command).then(() => {
                            new FilamentNotification()
                                .title('SSH Public Key Command Copied to Clipboard')
                                .success()
                                .send();
                        }).catch((error) => {
                            const isSecure = window.location.protocol === 'https:';
                            const title = 'Failed to copy to clipboard';
                            const body = isSecure
                                ? 'An error occurred while copying. Please try again or copy manually.'
                                : 'Clipboard API requires HTTPS. Please enable HTTPS or copy manually.';

                            new FilamentNotification()
                                .title(title)
                                .body(body)
                                .danger()
                                .send();
                        });
                    } else {
                        const isSecure = window.location.protocol === 'https:';
                        const body = isSecure
                            ? 'Your browser does not support the Clipboard API. Please copy manually.'
                            : 'Clipboard API requires HTTPS. Please enable HTTPS or copy manually.';

                        new FilamentNotification()
                            .title('Clipboard not supported')
                            .body(body)
                            .danger()
                            .send();
                    }
                JS,
            ])
            ->action(fn () => null);
    }
}
