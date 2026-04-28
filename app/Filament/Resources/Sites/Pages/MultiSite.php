<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\Schemas\MultiForm;
use App\Models\Hosting;
use Filament\Resources\Events\RecordCreated;
use Filament\Resources\Events\RecordSaved;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Throwable;

class MultiSite extends CreateSite
{
    protected static ?string $title = 'Create Multiple Sites';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return MultiForm::configure($schema);
    }

    public function create(bool $another = false): void
    {
        if ($this->isCreating) {
            return;
        }

        $this->isCreating = true;

        $this->authorizeAccess();

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeCreate($data);

            if (! $hosting = Hosting::find($data['hosting_id'])) {
                throw ValidationException::withMessages([
                    'hosting_id' => 'The selected hosting does not exist.',
                ]);
            }

            foreach ($data['sites'] as $siteData) {
                $this->callHook('beforeCreate');

                $this->record = $this->handleRecordCreation($siteData + [
                    'parent_id' => $data['parent_id'] ?? null,
                    'hosting_id' => $data['hosting_id'] ?? null,
                    'name' => $siteData['domain'],
                ]);

                $this->form->model($this->getRecord())->saveRelationships();

                $this->callHook('afterCreate');
                Event::dispatch(RecordCreated::class, ['record' => $this->getRecord(), 'data' => $data, 'page' => $this]);
                Event::dispatch(RecordSaved::class, ['record' => $this->getRecord(), 'data' => $data, 'page' => $this]);
            }

            $this->record = null;
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction() ?
                $this->rollBackDatabaseTransaction() :
                $this->commitDatabaseTransaction();

            $this->isCreating = false;

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            $this->isCreating = false;

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->rememberData();

        $this->getCreatedNotification()?->send();

        $redirectUrl = ListSites::getUrl();

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel())($data);

        if ($parentRecord = $this->getParentRecord()) {
            return $this->associateRecordWithParent($record, $parentRecord);
        }

        $record->save();

        return $record;
    }
}
