<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\StaffResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    /** @var User $record */
                    $record = $this->record;

                    $activeStatuses = collect(AppointmentStatus::cases())
                        ->filter(fn (AppointmentStatus $status) => $status->isActive())
                        ->map(fn (AppointmentStatus $status) => $status->value)
                        ->toArray();

                    $hasActiveAppointments = $record->appointments()
                        ->whereIn('status', $activeStatuses)
                        ->exists();

                    if ($hasActiveAppointments) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot delete staff member')
                            ->body('This staff member has active appointments and cannot be deleted.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
