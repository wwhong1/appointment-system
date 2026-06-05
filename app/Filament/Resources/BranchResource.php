<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Rules\E164PhoneRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('address')
                    ->required()
                    ->maxLength(500),

                TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->rules([new E164PhoneRule()]),

                Select::make('timezone')
                    ->required()
                    ->searchable()
                    ->options(
                        collect(timezone_identifiers_list())
                            ->mapWithKeys(fn (string $tz) => [$tz => $tz])
                            ->toArray()
                    ),

                TimePicker::make('opening_time')
                    ->required()
                    ->seconds(false),

                TimePicker::make('closing_time')
                    ->required()
                    ->seconds(false)
                    ->after('opening_time'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('phone')
                    ->searchable(),

                TextColumn::make('timezone')
                    ->sortable(),

                TextColumn::make('opening_time'),

                TextColumn::make('closing_time'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Branch $record) {
                        if ($record->appointments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete branch')
                                ->body('Cannot delete branch while it has associated appointments.')
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
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
