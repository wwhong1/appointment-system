<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Services\AppointmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * Override the default create behavior to use AppointmentService.
     * This ensures all business rules (operating hours, overlap, staff-branch) are enforced.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(AppointmentService::class);

        try {
            return $service->create($data);
        } catch (ValidationException $e) {
            // Convert validation errors to Filament notifications
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
}
