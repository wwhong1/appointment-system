<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use App\Models\Branch;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    /** @var Branch $record */
                    $record = $this->record;

                    if ($record->appointments()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot delete branch')
                            ->body('Cannot delete branch while it has associated appointments.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
