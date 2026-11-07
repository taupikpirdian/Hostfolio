<?php

namespace App\Filament\Resources\ServiceDeployments;

use App\Filament\Resources\ServiceDeployments\Pages\ManageServiceDeployments;
use App\Models\ServiceDeployment;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ServiceDeploymentResource extends Resource
{
    protected static ?string $model = ServiceDeployment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Select::make('service_id')->relationship('service', 'name')->searchable()->preload()->required(), Select::make('compute_instance_id')->relationship('instance', 'name')->searchable()->preload()->required(), Select::make('environment')->options(['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development'])->required(), TextInput::make('role')->maxLength(255), Toggle::make('is_primary'), DateTimePicker::make('deployed_at')->default(now())->required(), Textarea::make('notes')->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('service.name')->sortable()->searchable(), TextColumn::make('instance.name')->sortable()->searchable(), TextColumn::make('environment')->sortable(), TextColumn::make('role')->sortable(), IconColumn::make('is_primary')->boolean(), TextColumn::make('deployed_at')->sortable()->dateTime(), TextColumn::make('removed_at')->sortable()->dateTime()])->filters([])->recordActions([Action::make('remove')->label('End deployment')->authorize('update')->requiresConfirmation()->visible(fn (ServiceDeployment $record): bool => $record->removed_at === null)->action(function (ServiceDeployment $record): void {
            Gate::authorize('update', $record);
            $record->update(['removed_at' => now()]);
        })])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageServiceDeployments::route('/')];
    }
}
