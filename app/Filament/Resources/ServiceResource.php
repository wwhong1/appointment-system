<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('duration_minutes')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(480)
                    ->label('Duration (minutes)'),

                FileUpload::make('image')
                    ->image()
                    ->directory('services')
                    ->nullable(),

                TextInput::make('price')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(999999.99)
                    ->prefix('$')
                    ->nullable(),

                Textarea::make('description')
                    ->maxLength(1000)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label('Duration (min)')
                    ->sortable(),

                TextColumn::make('price')
                    ->money()
                    ->sortable(),

                ImageColumn::make('image'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Service $record) {
                        if ($record->appointments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete service')
                                ->body('Cannot delete service while it has associated appointments.')
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
