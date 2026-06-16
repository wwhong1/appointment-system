<?php

namespace App\Filament\Resources;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\StaffResource\Pages;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static ?string $slug = 'staff';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'staff');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->minLength(1)
                    ->maxLength(255),

                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(table: 'users', column: 'email', ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->password()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => bcrypt($state)),

                Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                        // Reset working hours when branch changes
                        $set('working_start_time', null);
                        $set('working_end_time', null);
                    }),

                TimePicker::make('working_start_time')
                    ->label('Working Hours Start')
                    ->nullable()
                    ->seconds(false)
                    ->helperText(function (Get $get): string {
                        $branch = $get('branch_id') ? Branch::find($get('branch_id')) : null;
                        if ($branch) {
                            return "Must be at or after branch opening time ({$branch->opening_time}, {$branch->timezone}).";
                        }
                        return 'Optional. Select a branch first.';
                    })
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, $fail) use ($get) {
                                if (!$value) return;
                                $branchId = $get('branch_id');
                                if (!$branchId) return;
                                $branch = Branch::find($branchId);
                                if (!$branch) return;

                                $staffTime = Carbon::parse($value)->format('H:i:s');
                                $openingTime = Carbon::parse($branch->opening_time)->format('H:i:s');
                                $closingTime = Carbon::parse($branch->closing_time)->format('H:i:s');

                                if ($staffTime < $openingTime) {
                                    $fail("Working hours start cannot be before branch opening time ({$branch->opening_time}).");
                                }
                                if ($staffTime >= $closingTime) {
                                    $fail("Working hours start must be before branch closing time ({$branch->closing_time}).");
                                }
                            };
                        },
                    ]),

                TimePicker::make('working_end_time')
                    ->label('Working Hours End')
                    ->nullable()
                    ->seconds(false)
                    ->helperText(function (Get $get): string {
                        $branch = $get('branch_id') ? Branch::find($get('branch_id')) : null;
                        if ($branch) {
                            return "Must be at or before branch closing time ({$branch->closing_time}, {$branch->timezone}).";
                        }
                        return 'Optional. Both start and end must be set to enforce working hours.';
                    })
                    ->after('start_datetime')
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, $fail) use ($get) {
                                if (!$value) return;
                                $branchId = $get('branch_id');
                                if (!$branchId) return;
                                $branch = Branch::find($branchId);
                                if (!$branch) return;

                                $staffTime = Carbon::parse($value)->format('H:i:s');
                                $openingTime = Carbon::parse($branch->opening_time)->format('H:i:s');
                                $closingTime = Carbon::parse($branch->closing_time)->format('H:i:s');

                                if ($staffTime > $closingTime) {
                                    $fail("Working hours end cannot be after branch closing time ({$branch->closing_time}).");
                                }
                                if ($staffTime <= $openingTime) {
                                    $fail("Working hours end must be after branch opening time ({$branch->opening_time}).");
                                }
                            };
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable(),

                TextColumn::make('working_start_time')
                    ->label('Work Start')
                    ->sortable(),

                TextColumn::make('working_end_time')
                    ->label('Work End')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, User $record) {
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
