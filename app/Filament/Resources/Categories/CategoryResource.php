<?php

namespace App\Filament\Resources\Categories;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->unique(ignoreRecord: true), Select::make('type')->options(['expense' => 'Expense', 'inventory' => 'Inventory'])->required(), Textarea::make('notes')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('type')->badge()])->defaultSort('id', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
