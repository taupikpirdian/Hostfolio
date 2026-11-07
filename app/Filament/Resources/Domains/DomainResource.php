<?php

namespace App\Filament\Resources\Domains;

use App\Filament\Resources\Domains\Pages\ManageDomains;
use App\Models\Domain;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'fqdn';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('fqdn')->mutateStateForValidationUsing(fn (?string $state): string => strtolower(rtrim(trim($state ?? ''), '.')))->dehydrateStateUsing(fn (?string $state): string => strtolower(rtrim(trim($state ?? ''), '.')))->maxLength(255)->required()->unique(ignoreRecord: true)->regex('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/'), TextInput::make('registrar')->maxLength(255), DatePicker::make('expires_at'), Toggle::make('auto_renew'), Select::make('status')->options(['active' => 'Active', 'expired' => 'Expired', 'inactive' => 'Inactive'])->required(), Select::make('services')->relationship('services', 'name')->searchable()->preload()->multiple(), Textarea::make('notes')->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('fqdn')->sortable()->searchable(), TextColumn::make('registrar')->sortable(), TextColumn::make('expires_at')->sortable()->date(), IconColumn::make('auto_renew')->boolean(), TextColumn::make('status')->sortable()->badge()])->filters([SelectFilter::make('status')->options(['active' => 'Active', 'expired' => 'Expired', 'inactive' => 'Inactive'])])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDomains::route('/')];
    }
}
