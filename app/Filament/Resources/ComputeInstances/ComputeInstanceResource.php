<?php

namespace App\Filament\Resources\ComputeInstances;

use App\Actions\Inventory\SaveComputeInstance;
use App\Actions\Inventory\SetResourceAllocation;
use App\Filament\Resources\ComputeInstances\Pages\ManageComputeInstances;
use App\Filament\Resources\ComputeInstances\Pages\ViewComputeInstance;
use App\Models\ComputeInstance;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ComputeInstanceResource extends Resource
{
    protected static ?string $model = ComputeInstance::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->maxLength(255)->required(), Select::make('type')->options(['vm' => 'Vm', 'lxc' => 'Lxc', 'docker' => 'Docker', 'vps' => 'Vps', 'shared_hosting' => 'Shared Hosting'])->required(), Select::make('provider_id')->relationship('provider', 'name')->searchable()->preload(), Select::make('physical_server_id')->relationship('physicalServer', 'name')->searchable()->preload(), Select::make('parent_id')->relationship('parent', 'name')->searchable()->preload(), TextInput::make('hostname')->maxLength(255), TextInput::make('ip_address')->maxLength(255)->ip(), TextInput::make('operating_system')->maxLength(255), TextInput::make('external_reference')->maxLength(255), Select::make('status')->options(['planned' => 'Planned', 'active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired'])->required(), Textarea::make('notes')->columnSpanFull(), Section::make('Resource Allocation')->schema([TextInput::make('allocation_vcpu')->numeric()->minValue(0)->label('vCPU'), TextInput::make('allocation_ram_gb')->numeric()->minValue(0)->label('RAM (GB)'), TextInput::make('allocation_storage_gb')->numeric()->minValue(0)->label('Storage (GB)'), Textarea::make('allocation_notes')->label('Allocation notes')->columnSpanFull(), Placeholder::make('allocation_history')->label('Riwayat alokasi')->content(fn (?ComputeInstance $record): HtmlString => new HtmlString(view('filament.resources.compute-instances.allocation-history', ['allocations' => $record?->allocations()->with('changedBy')->latest('effective_from')->get() ?? collect()])->render()))->visible(fn (?ComputeInstance $record): bool => $record !== null)->columnSpanFull()])->columns(3)->columnSpanFull()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->sortable()->searchable(['name', 'code'])->description(fn (ComputeInstance $record): ?string => $record->code)->url(fn (ComputeInstance $record): string => static::getUrl('view', ['record' => $record])), TextColumn::make('type')->sortable()->badge(), TextColumn::make('physicalServer.name')->sortable()->searchable(), TextColumn::make('currentAllocation.vcpu')->label('vCPU')->sortable(), TextColumn::make('currentAllocation.ram_mb')->label('RAM (GB)')->formatStateUsing(fn (?int $state): ?float => $state === null ? null : round($state / 1024, 2))->sortable(), TextColumn::make('status')->sortable()->badge()->color(fn (?string $state): ?string => $state === 'active' ? 'success' : null)])->filters([SelectFilter::make('status')->options(['planned' => 'Planned', 'active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired']), SelectFilter::make('provider')->relationship('provider', 'name'), SelectFilter::make('physical_server')->relationship('physicalServer', 'name'), SelectFilter::make('type')->options(['vm' => 'VM', 'lxc' => 'LXC', 'docker' => 'Docker', 'vps' => 'VPS', 'shared_hosting' => 'Shared hosting'])])->recordActions([EditAction::make()->mutateRecordDataUsing(function (array $data, ComputeInstance $record): array {
            $current = $record->currentAllocation;

            return array_merge($data, ['allocation_vcpu' => $current?->vcpu, 'allocation_ram_gb' => $current?->ram_mb === null ? null : $current->ram_mb / 1024, 'allocation_storage_gb' => $current?->storage_gb]);
        })->using(fn (ComputeInstance $record, array $data): ComputeInstance => static::saveWithAllocation($data, $record))])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageComputeInstances::route('/'), 'view' => ViewComputeInstance::route('/{record}')];
    }

    public static function saveWithAllocation(array $data, ?ComputeInstance $record = null): ComputeInstance
    {
        return DB::transaction(function () use ($data, $record): ComputeInstance {
            $allocation = [
                'vcpu' => $data['allocation_vcpu'] ?? null,
                'ram_mb' => filled($data['allocation_ram_gb'] ?? null) ? (int) round((float) $data['allocation_ram_gb'] * 1024) : null,
                'storage_gb' => $data['allocation_storage_gb'] ?? null,
            ];
            $notes = $data['allocation_notes'] ?? null;
            $instance = app(SaveComputeInstance::class)->execute($data, $record);
            $current = $instance->currentAllocation()->first();
            $changed = $current && collect($allocation)->contains(function ($value, $key) use ($current): bool {
                $previous = $current->{$key};

                return filled($value) || filled($previous)
                    ? (filled($value) !== filled($previous) || (float) $value !== (float) $previous)
                    : false;
            });

            if (($current === null && count(array_filter($allocation, fn ($value): bool => filled($value))) > 0) || $changed || filled($notes)) {
                app(SetResourceAllocation::class)->execute($instance, [...$allocation, 'notes' => $notes]);
            }

            return $instance;
        });
    }
}
