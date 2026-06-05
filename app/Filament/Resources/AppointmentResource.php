<?php

namespace App\Filament\Resources;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Services\AppointmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('staff_id', null);
                    }),

                Select::make('staff_id')
                    ->label('Staff')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function (Get $get) {
                        $branchId = $get('branch_id');
                        if (!$branchId) {
                            return [];
                        }
                        return User::query()
                            ->where('role', 'staff')
                            ->where('branch_id', $branchId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),

                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('service_id')
                    ->relationship('service', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                DateTimePicker::make('start_datetime')
                    ->required()
                    ->seconds(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_datetime')
                    ->label('Start')
                    ->dateTime()
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
                    ->dateTime()
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

                TextColumn::make('staff.name')
                    ->label('Staff')
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
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('date')
                            ->label('Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['date']) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($data) {
                            $date = $data['date'];

                            // Filter appointments by date evaluated in branch timezone
                            $query->whereHas('branch', function (Builder $branchQuery) use ($query, $date) {
                                // We need to get all branches and filter appointments
                                // whose start_datetime falls on the selected date in the branch's timezone
                            });

                            // Get all branches with their timezones
                            $branches = Branch::all();

                            $query->where(function (Builder $q) use ($branches, $date) {
                                foreach ($branches as $branch) {
                                    $timezone = $branch->timezone;
                                    $startOfDay = \Carbon\Carbon::parse($date, $timezone)->startOfDay()->utc();
                                    $endOfDay = \Carbon\Carbon::parse($date, $timezone)->endOfDay()->utc();

                                    $q->orWhere(function (Builder $subQuery) use ($branch, $startOfDay, $endOfDay) {
                                        $subQuery->where('branch_id', $branch->id)
                                            ->where('start_datetime', '>=', $startOfDay)
                                            ->where('start_datetime', '<=', $endOfDay);
                                    });
                                }
                            });
                        });
                    }),
            ])
            ->actions([
                EditAction::make(),
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
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_datetime', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit' => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
