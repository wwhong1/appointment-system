<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->appointments()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot delete service')
                            ->body('Cannot delete service while it has associated appointments.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
