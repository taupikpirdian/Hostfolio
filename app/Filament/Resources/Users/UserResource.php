<?php

namespace App\Filament\Resources\Users;

use App\Models\Role;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(255), TextInput::make('email')->email()->required()->unique(ignoreRecord: true), TextInput::make('password')->password()->revealable()->minLength(12)->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state)), Select::make('role')->options(fn () => Role::pluck('name', 'name'))->required()->default('viewer'), Toggle::make('is_active')->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('email')->searchable(), TextColumn::make('role')->badge(), IconColumn::make('is_active')->boolean()])->defaultSort('id', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
