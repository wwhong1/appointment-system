<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * Prevent editing appointments with terminal status.
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        /** @var Appointment $record */
        $record = $this->getRecord();

        if ($record->status->isTerminal()) {
            Notification::make()
                ->danger()
                ->title('Cannot edit appointment')
                ->body("Cannot modify an appointment with status {$record->status->value}.")
                ->send();

            $this->redirect(AppointmentResource::getUrl('index'));
        }
    }

    /**
     * Override the default save behavior to use AppointmentService.
     * This ensures all business rules are re-validated on update.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(AppointmentService::class);

        try {
            return $service->update($record, $data);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Notification::make()
                        ->danger()
                        ->title('Validation Error')
                        ->body($message)
                        ->send();
                }
            }

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
