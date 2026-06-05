<?php

namespace App\Filament\Staff\Resources;

use App\Enums\AppointmentStatus;
use App\Filament\Staff\Resources\StaffAppointmentResource\Pages;
use App\Models\Appointment;
use App\Services\AppointmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffAppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'My Appointments';

    protected static ?string $modelLabel = 'Appointment';

    protected static ?string $pluralModelLabel = 'Appointments';

    /**
     * Scope the query to only show appointments assigned to the authenticated staff member.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('staff_id', Auth::id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_datetime')
                    ->label('Start')
                    ->sortable()
                    ->formatStateUsing(function ($state, Appointment $record) {
                        if (!$record->branch || !$state) {
                            return $state;
                        }
                        return \Carbon\Carbon::parse($state)
                            ->setTimezone($record->branch->timezone)
                            ->format('Y-m-d H:i');
                    }),

                TextColumn::make('end_datetime')
                    ->label('End')
                    ->sortable()
                    ->formatStateUsing(function ($state, Appointment $record) {
                        if (!$record->branch || !$state) {
                            return $state;
                        }
                        return \Carbon\Carbon::parse($state)
                            ->setTimezone($record->branch->timezone)
                            ->format('Y-m-d H:i');
                    }),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('service.name')
                    ->label('Service')
                    ->sortable(),

                TextColumn::make('status')
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
            ])
            ->actions([
                Action::make('transition')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->hidden(fn (Appointment $record): bool => $record->status->isTerminal())
                    ->form([
                        Select::make('new_status')
                            ->label('New Status')
                            ->required()
                            ->options(function (Appointment $record): array {
                                return collect($record->status->validTransitions())
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
                    ->action(function (Appointment $record, array $data): void {
                        $service = app(AppointmentService::class);

                        try {
                            $service->transitionStatus(
                                $record,
                                AppointmentStatus::from($data['new_status']),
                                $data['cancellation_reason'] ?? null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Status updated')
                                ->body("Appointment status changed to {$data['new_status']}.")
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Status transition failed')
                                ->body(collect($e->errors())->flatten()->first())
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('start_datetime', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffAppointments::route('/'),
            'view' => Pages\ViewStaffAppointment::route('/{record}'),
        ];
    }
}
