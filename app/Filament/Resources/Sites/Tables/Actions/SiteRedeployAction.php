<?php

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Actions\DeploySite;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class SiteRedeployAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'redeploy-site';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Redeploy'));
        $this->modalHeading(fn (): string => __('Confirm Redeploy'));
        $this->successNotificationTitle(__('Redeploying'));
        $this->requiresConfirmation();
        $this->modalContent(new HtmlString(
            '<strong>This will delete existing site and copy from parent site again.</strong>'
        ));
        $this->color(Color::Red);
        $this->icon('heroicon-o-arrow-path');

        $this->action(function (Model $record, array $data) {
            if (Hash::check($data['password'], Filament::auth()->user()->password)) {
                Notification::make()
                    ->title('Invalid password')
                    ->body('The password you entered is incorrect')
                    ->danger()
                    ->send();

                $this->failure();

                return new Halt;
            }

            (new DeploySite)->handle($record);
        });

        $this->schema([
            TextInput::make('password')
                ->password()
                ->placeholder('Enter password to redeploy')
                ->required(),
        ]);
    }
}
