<?php

namespace App\Filament\Staff\Resources\StaffAppointmentResource\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Staff\Resources\StaffAppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ViewStaffAppointment extends ViewRecord
{
    protected static string $resource = StaffAppointmentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Appointment Details')
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Branch'),

                        TextEntry::make('staff.name')
                            ->label('Staff'),

                        TextEntry::make('customer.name')
                            ->label('Customer'),

                        TextEntry::make('service.name')
                            ->label('Service'),

                        TextEntry::make('start_datetime')
                            ->label('Start')
                            ->formatStateUsing(function ($state, Appointment $record) {
                                if (!$record->branch || !$state) {
                                    return $state;
                                }
                                return \Carbon\Carbon::parse($state)
                                    ->setTimezone($record->branch->timezone)
                                    ->format('Y-m-d H:i');
                            }),

                        TextEntry::make('end_datetime')
                            ->label('End')
                            ->formatStateUsing(function ($state, Appointment $record) {
                                if (!$record->branch || !$state) {
                                    return $state;
                                }
                                return \Carbon\Carbon::parse($state)
                                    ->setTimezone($record->branch->timezone)
                                    ->format('Y-m-d H:i');
                            }),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (AppointmentStatus $state): string => match ($state) {
                                AppointmentStatus::Pending => 'warning',
                                AppointmentStatus::Confirmed => 'info',
                                AppointmentStatus::InProgress => 'primary',
                                AppointmentStatus::Completed => 'success',
                                AppointmentStatus::Cancelled => 'danger',
                                AppointmentStatus::NoShow => 'gray',
                            })
                            ->formatStateUsing(fn (AppointmentStatus $state): string => match ($state) {
                                AppointmentStatus::Pending => 'Pending',
                                AppointmentStatus::Confirmed => 'Confirmed',
                                AppointmentStatus::InProgress => 'In Progress',
                                AppointmentStatus::Completed => 'Completed',
                                AppointmentStatus::Cancelled => 'Cancelled',
                                AppointmentStatus::NoShow => 'No Show',
                            }),

                        TextEntry::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->visible(fn (Appointment $record): bool => $record->status === AppointmentStatus::Cancelled && $record->cancellation_reason !== null),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transition')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->hidden(fn (): bool => $this->getRecord()->status->isTerminal())
                ->form([
                    Select::make('new_status')
                        ->label('New Status')
                        ->required()
                        ->options(function (): array {
                            return collect($this->getRecord()->status->validTransitions())
                                ->mapWithKeys(fn (AppointmentStatus $status) => [
                                    $status->value => match ($status) {
                                        AppointmentStatus::Pending => 'Pending',
                                        AppointmentStatus::Confirmed => 'Confirmed',
                                        AppointmentStatus::InProgress => 'In Progress',
                                        AppointmentStatus::Completed => 'Completed',
                                        AppointmentStatus::Cancelled => 'Cancelled',
                                        AppointmentStatus::NoShow => 'No Show',
                                    },
                                ])
                                ->toArray();
                        })
                        ->live(),

                    Textarea::make('cancellation_reason')
                        ->label('Cancellation Reason')
                        ->maxLength(500)
                        ->visible(fn (Get $get): bool => $get('new_status') === AppointmentStatus::Cancelled->value)
                        ->required(fn (Get $get): bool => $get('new_status') === AppointmentStatus::Cancelled->value),
                ])
                ->action(function (array $data): void {
                    $service = app(AppointmentService::class);

                    try {
                        $service->transitionStatus(
                            $this->getRecord(),
                            AppointmentStatus::from($data['new_status']),
                            $data['cancellation_reason'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title('Status updated')
                            ->body("Appointment status changed to {$data['new_status']}.")
                            ->send();

                        $this->refreshFormData(['status', 'cancellation_reason']);
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Status transition failed')
                            ->body(collect($e->errors())->flatten()->first())
                            ->send();
                    }
                }),
        ];
    }
}
