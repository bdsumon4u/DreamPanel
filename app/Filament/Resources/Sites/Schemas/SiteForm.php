<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Enums\HostingProvider;
use App\Models\Hosting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Operation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SiteForm
{
    protected static function sshSection(): Component
    {
        return Section::make('SSH')
            ->collapsed()
            ->compact()
            ->schema([
                Grid::make(3)
                    ->dense()
                    ->schema([
                        TextInput::make('key_name')
                            ->label('Key Name')
                            ->hintColor(Color::Red)
                            ->formatStateUsing(fn () => 'HotashTech')
                            ->hint(new HtmlString('Must be <strong>HotashTech</strong>'))
                            ->hintIcon('heroicon-o-exclamation-circle'),
                        TextInput::make('private_key')
                            ->label('Private Key')
                            ->hint('Empty'),
                        TextInput::make('passphrase')
                            ->label('Passphrase')
                            ->hint('Empty'),
                    ]),
                Textarea::make('public_key')
                    ->formatStateUsing(fn () => Storage::drive('local')->get('HotashTech.pub'))
                    ->label('Public Key')
                    ->rows(8),
            ])
            ->disabled()
            ->collapsible()
            ->columnSpanFull();
    }

    protected static function databaseNameField(): Component
    {
        return TextInput::make('database_name')
            ->label('Name')
            ->alphaDash()
            ->required()
            ->suffixAction(
                Action::make('generate')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn ($component) => $component->state(Str::random(6)))
            );
    }

    protected static function databaseUsernameField(): Component
    {
        return TextInput::make('database_user')
            ->label('Username')
            ->alphaDash()
            ->required()
            ->suffixAction(
                Action::make('generate')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn ($component) => $component->state(Str::random(6)))
            );
    }

    protected static function databasePasswordField(): Component
    {
        return TextInput::make('database_pass')
            ->label('Password')
            ->default(fn (Get $get) => $get('../../database_pass') ?? 'Hotash<DB>Pass')
            ->required(fn (string $operation) => $operation !== Operation::Edit->value)
            ->suffixAction(
                Action::make('generate')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn ($component) => $component->state(Str::random(10)))
            );
    }

    protected static function databaseSection(): Component
    {
        return Section::make('Database')
            ->compact()
            ->schema([
                self::databaseNameField(),
                self::databaseUsernameField(),
                self::databasePasswordField(),
            ]);
    }

    protected static function emailUsernameFile(): Component
    {
        return TextInput::make('email_username')
            ->label('Username')
            ->email()
            ->required();
    }

    protected static function emailPasswordField(): Component
    {
        return TextInput::make('email_password')
            ->label('Password')
            ->required(fn (string $operation) => $operation !== Operation::Edit->value)
            ->default(fn (Get $get) => $get('../../email_password') ?? 'Hotash<Email>Pass')
            ->suffixAction(
                Action::make('generate')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn ($component) => $component->state(Str::random(10)))
            );
    }

    protected static function emailSection(string $statePrefix = ''): Component
    {
        return Section::make('Mail')
            ->collapsed()
            ->compact()
            ->schema([
                self::emailUsernameFile(),
                self::emailPasswordField(),
            ])
            ->visible(fn (Get $get): bool => ! self::isCloudPanelHosting($get, $statePrefix));
    }

    protected static function copyFromField(): Component
    {
        return Select::make('parent_id')
            ->label('Copy From')
            ->relationship('parent', 'domain')
            ->preload()
            ->searchable();
    }

    protected static function siteNameField(): Component
    {
        return TextInput::make('name')
            ->required();
    }

    protected static function siteUserField(string $statePrefix = ''): Component
    {
        return TextInput::make('site_user')
            ->label('Site User')
            ->maxLength(24)
            ->helperText('Optional for CloudPanel. Leave empty to auto-generate.')
            ->visible(fn (Get $get): bool => self::isCloudPanelHosting($get, $statePrefix))
            ->disabled(function (Get $get) use ($statePrefix) {
                return ! $get($statePrefix.'hosting_id') || ! $get($statePrefix.'limit');
            });
    }

    protected static function sitePasswordField(string $statePrefix = ''): Component
    {
        return TextInput::make('site_password')
            ->label('Site Password')
            ->password()
            ->revealable()
            ->default(fn (Get $get) => $get('../../site_password') ?? 'Password123!')
            ->helperText('Optional for CloudPanel. Leave empty to use fallback password.')
            ->visible(fn (Get $get): bool => self::isCloudPanelHosting($get, $statePrefix))
            ->disabled(function (Get $get) use ($statePrefix) {
                return ! $get($statePrefix.'hosting_id') || ! $get($statePrefix.'limit');
            });
    }

    protected static function isCloudPanelHosting(Get $get, string $statePrefix = ''): bool
    {
        return $get($statePrefix.'hosting_provider') === HostingProvider::CloudPanel->value;
    }

    protected static function hostingField(): Component
    {
        return Select::make('hosting_id')
            ->relationship('hosting', 'domain', function ($query) {
                $query->withCount('sites');
            })
            ->required()
            ->searchable(['domain', 'username'])
            ->preload()
            ->live()
            ->getOptionLabelFromRecordUsing(function (?Model $record) {
                return $record ? $record->domain.' ('.$record->sites_count.' / '.$record->site_limit.')' : '';
            })
            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                if (! $state) {
                    $set('hosting_domain', null);
                    $set('limit', null);
                    $set('hosting_provider', null);

                    return;
                }

                $hosting = Hosting::select([
                    'id', 'domain', 'provider', 'site_limit',
                ])->withCount('sites')->findOrFail($state);
                $set('hosting_domain', $hosting->domain);
                $set('limit', max($hosting->site_limit - $hosting->sites_count, 0));
                $set('hosting_provider', $hosting->provider?->value ?? $hosting->provider);

                if (
                    ($hosting->provider?->value ?? $hosting->provider) === HostingProvider::CloudPanel->value
                    && blank($get('site_user'))
                    && filled($get('domain'))
                ) {
                    $siteUser = Str::lower((string) preg_replace('/[^a-z0-9]/', '', (string) $get('domain')));
                    if ($siteUser === '' || ctype_digit($siteUser[0])) {
                        $siteUser = 'site'.$siteUser;
                    }

                    $set('site_user', substr($siteUser, 0, 24));
                }
            })
            ->hint(function (Get $get) {
                if (! is_null($get('limit'))) {
                    return $get('limit').' slot(s) remaining';
                }
            });
    }

    protected static function domainField(string $statePrefix = ''): Component
    {
        return TextInput::make('domain')
            ->required()
            ->live(true)
            ->disabled(function (Get $get) use ($statePrefix) {
                return ! $get($statePrefix.'hosting_id') || ! $get($statePrefix.'limit');
            })
            ->unique(ignoreRecord: true)
            ->rules([
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            ])
            ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($statePrefix) {
                $set('directory', $state === $get($statePrefix.'hosting_domain') ? 'public_html' : $state);
                $set('email_username', 'support@'.$state);
                $set('database_name', Str::slug($state, '_'));
                $set('database_user', Str::slug($state, '_'));

                if (self::isCloudPanelHosting($get, $statePrefix) && blank($get($statePrefix.'site_user'))) {
                    $siteUser = Str::lower((string) preg_replace('/[^a-z0-9]/', '', $state));
                    if ($siteUser === '' || ctype_digit($siteUser[0])) {
                        $siteUser = 'site'.$siteUser;
                    }

                    $set($statePrefix.'site_user', substr($siteUser, 0, 24));
                }
            });
    }

    protected static function directoryField(string $statePrefix = ''): Component
    {
        return TextInput::make('directory')
            ->regex('/^[a-zA-Z0-9._-]+$/')
            ->disabled(function (Get $get) use ($statePrefix) {
                return ! $get($statePrefix.'hosting_id') || ! $get($statePrefix.'limit');
            })
            ->required();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                self::sshSection(),
                Group::make([
                    self::copyFromField(),
                    self::siteNameField(),
                    self::hostingField()
                        ->columnSpanFull(),
                    self::domainField(),
                    self::directoryField(),
                ])
                    ->dense()
                    ->columns(2)
                    ->columnSpan(2),
                Group::make([
                    self::emailSection()
                        ->collapsed(false)
                        ->columns(2)
                        ->columnSpanFull(),
                    self::siteUserField(),
                    self::sitePasswordField(),
                    self::databaseSection()
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                    ->columns(2)
                    ->dense()
                    ->columnSpan(3),
            ])
            ->columns(5);
    }
}
