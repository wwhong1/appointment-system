<?php

namespace App\Filament\Resources;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\StaffResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
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
                    ->preload(),
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
