<?php

namespace App\Filament\Resources\Providers;

use App\Filament\Resources\Providers\Pages\ManageProviders;
use App\Models\Provider;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->maxLength(255)->required()->unique(ignoreRecord: true), Select::make('type')->options(['internal' => 'Internal', 'external' => 'External'])->required(), TextInput::make('website')->maxLength(255)->url(), Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(), Textarea::make('notes')->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->sortable()->searchable(), TextColumn::make('type')->sortable()->badge(), TextColumn::make('status')->sortable()->badge()])->filters([SelectFilter::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageProviders::route('/')];
    }
}
