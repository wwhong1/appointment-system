<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->appointments()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot delete customer')
                            ->body('Cannot delete customer while it has associated appointments.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
