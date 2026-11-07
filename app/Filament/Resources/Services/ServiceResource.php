<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\PhysicalServer;
use App\Models\Provider;
use App\Models\Service;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->maxLength(255)->required(), Select::make('type')->options(['website' => 'Website', 'api' => 'Api', 'database' => 'Database', 'worker' => 'Worker', 'internal_application' => 'Internal Application', 'other' => 'Other'])->required(), Select::make('party_id')->relationship('client', 'name')->searchable()->preload(), Select::make('status')->options(['draft' => 'Draft', 'running' => 'Running', 'stopped' => 'Stopped', 'archived' => 'Archived'])->required(), DatePicker::make('started_at'), Textarea::make('description')->columnSpanFull(), Select::make('domains')->relationship('domains', 'fqdn')->searchable()->preload()->multiple(), Textarea::make('notes')->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('code')->sortable()->searchable(), TextColumn::make('name')->sortable()->searchable(), TextColumn::make('slug')->sortable()->searchable(), TextColumn::make('type')->sortable()->badge(), TextColumn::make('client.name')->sortable()->searchable(), TextColumn::make('status')->sortable()->badge(), TextColumn::make('started_at')->sortable()->date()])->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'running' => 'Running', 'stopped' => 'Stopped', 'archived' => 'Archived']), SelectFilter::make('client')->relationship('client', 'name'), SelectFilter::make('domain')->relationship('domains', 'fqdn'), SelectFilter::make('type')->options(['website' => 'Website', 'api' => 'API', 'database' => 'Database', 'worker' => 'Worker', 'internal_application' => 'Internal application', 'other' => 'Other']),
            SelectFilter::make('physical_server')->label('Physical server')->options(fn (): array => PhysicalServer::query()->orderBy('name')->pluck('name', 'id')->all())->query(function (Builder $query, array $data): Builder {
                return $query->when($data['value'], fn (Builder $query, $value): Builder => $query->whereHas('deployments', fn (Builder $deployments): Builder => $deployments->whereNull('removed_at')->whereHas('instance', fn (Builder $instances): Builder => $instances->where('physical_server_id', $value))));
            }),
            SelectFilter::make('provider')->label('Provider')->options(fn (): array => Provider::query()->orderBy('name')->pluck('name', 'id')->all())->query(function (Builder $query, array $data): Builder {
                return $query->when($data['value'], fn (Builder $query, $value): Builder => $query->whereHas('deployments', fn (Builder $deployments): Builder => $deployments->whereNull('removed_at')->whereHas('instance', fn (Builder $instances): Builder => $instances->where('provider_id', $value))));
            }),
            SelectFilter::make('instance_type')->label('Compute instance type')->options(['vm' => 'VM', 'lxc' => 'LXC', 'docker' => 'Docker', 'vps' => 'VPS', 'shared_hosting' => 'Shared hosting'])->query(function (Builder $query, array $data): Builder {
                return $query->when($data['value'], fn (Builder $query, $value): Builder => $query->whereHas('deployments', fn (Builder $deployments): Builder => $deployments->whereNull('removed_at')->whereHas('instance', fn (Builder $instances): Builder => $instances->where('type', $value))));
            }),
        ])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }
}
