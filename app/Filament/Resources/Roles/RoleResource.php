<?php

namespace App\Filament\Resources\Roles;

use App\Models\Role;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->disabled(fn (string $operation) => $operation === 'edit')->required()->maxLength(100)->unique(ignoreRecord: true), CheckboxList::make('permissions')->options(['*' => 'All permissions (administrator)', 'inventory.view' => 'View inventory', 'inventory.manage' => 'Manage inventory', 'billing.view' => 'View billing', 'billing.manage' => 'Manage billing', 'settings.view' => 'View settings', 'settings.manage' => 'Manage settings'])->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('permissions')->badge()])->defaultSort('id', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRoles::route('/')];
    }
}
