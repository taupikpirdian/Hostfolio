<?php

namespace App\Filament\Resources\Parties;

use App\Filament\Resources\Parties\Pages\ManageParties;
use App\Models\Party;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required()->maxLength(255), Select::make('type')->options(['client' => 'Client', 'vendor' => 'Vendor', 'both' => 'Both'])->required()->default('client'), TextInput::make('email')->email()->maxLength(255), TextInput::make('phone')->maxLength(255), Textarea::make('address'), Textarea::make('tax_information'), Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active')->required(), Textarea::make('notes')]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('name'), TextEntry::make('type'), TextEntry::make('email'), TextEntry::make('phone'), TextEntry::make('address'), TextEntry::make('tax_information'), TextEntry::make('status'), TextEntry::make('notes')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()->sortable(), TextColumn::make('type')->badge(), TextColumn::make('email')->searchable(), TextColumn::make('status')->badge()])->filters([SelectFilter::make('type')->options(['client' => 'Client', 'vendor' => 'Vendor', 'both' => 'Both'])])->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageParties::route('/')];
    }
}
