<?php

namespace App\Filament\Resources\PhysicalServers;

use App\Filament\Resources\PhysicalServers\Pages\ManagePhysicalServers;
use App\Models\PhysicalServer;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PhysicalServerResource extends Resource
{
    protected static ?string $model = PhysicalServer::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->maxLength(255)->required(), TextInput::make('hostname')->maxLength(255), TextInput::make('location')->maxLength(255), Select::make('provider_id')->relationship('provider', 'name')->searchable()->preload(), TextInput::make('cpu_model')->maxLength(255), TextInput::make('cpu_cores')->numeric()->minValue(0)->integer(), TextInput::make('ram_mb')->numeric()->minValue(0)->integer()->label('Total RAM (MB)'), TextInput::make('storage_gb')->numeric()->minValue(0)->label('Provisioned storage capacity (GB)'), TextInput::make('operating_system')->maxLength(255), TextInput::make('management_ip')->maxLength(255)->ip(), Select::make('status')->options(['planned' => 'Planned', 'active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired'])->required(), Textarea::make('notes')->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->with('directInstances.currentAllocation'))->columns([TextColumn::make('code')->sortable()->searchable(), TextColumn::make('name')->sortable()->searchable(), TextColumn::make('provider.name')->sortable()->searchable(), TextColumn::make('cpu_cores')->sortable(), TextColumn::make('ram_mb')->sortable(), TextColumn::make('storage_gb')->sortable(), TextColumn::make('status')->sortable()->badge(), TextColumn::make('allocated_cpu')->label('Allocated vCPU / capacity')->state(function (PhysicalServer $record): string {
            $summary = $record->allocationSummary();

            return $summary['vcpu'].' / '.($record->cpu_cores ?? '?').($summary['cpu_overcommitted'] ? ' — overcommit' : '');
        })])->filters([SelectFilter::make('status')->options(['planned' => 'Planned', 'active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired']), SelectFilter::make('provider')->relationship('provider', 'name')])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhysicalServers::route('/')];
    }
}
