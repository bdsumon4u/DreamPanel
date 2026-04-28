<?php

namespace App\Filament\Resources\Sites\Tables\Actions;

use App\Enums\SiteStatus;
use App\Jobs\DeleteDomain;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class SiteDeleteAction extends DeleteAction
{
    public static function getDefaultName(): ?string
    {
        return 'delete-site';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modalHeading(fn (): string => __('Delete Site'));
        $this->successNotificationTitle(__('Deleting'));

        $this->using(static function (Model $record): ?bool {
            DeleteDomain::dispatch($record)->onQueue('low');

            return $record->update(['status' => SiteStatus::PENDING_DELETE]);
        });

        $this->hidden(static function (Model $record): bool {
            if (! method_exists($record, 'trashed')) {
                return false;
            }

            return $record->trashed() && $record->status !== SiteStatus::DELETE_FAILED;
        });
    }
}
