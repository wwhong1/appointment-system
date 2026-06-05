<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Rules\E164PhoneRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->nullable()
                    ->maxLength(255)
                    ->requiredWithout('phone')
                    ->validationMessages([
                        'required_without' => 'At least one contact method (email or phone) is required.',
                    ]),

                TextInput::make('phone')
                    ->tel()
                    ->nullable()
                    ->requiredWithout('email')
                    ->rules(['nullable', new E164PhoneRule()])
                    ->validationMessages([
                        'required_without' => 'At least one contact method (email or phone) is required.',
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
                    ->searchable(),

                TextColumn::make('phone')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Customer $record) {
                        if ($record->appointments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete customer')
                                ->body('Cannot delete customer while it has associated appointments.')
                                ->send();

                            $action->cancel();
                        }
                    }),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
