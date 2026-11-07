<?php

namespace App\Filament\Resources\BillingContracts;

use App\Actions\Billing\GenerateInvoice;
use App\Filament\Resources\BillingContracts\Pages\ManageBillingContracts;
use App\Models\BillingContract;
use App\Models\ComputeInstance;
use App\Models\Domain;
use App\Models\PhysicalServer;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BillingContractResource extends Resource
{
    protected static ?string $model = BillingContract::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('number')->required()->unique(ignoreRecord: true)->maxLength(255), Select::make('party_id')->relationship('party', 'name')->searchable()->preload()->required(), Select::make('direction')->options(['income' => 'Income', 'expense' => 'Expense'])->required(), Textarea::make('description'), DatePicker::make('start_date')->required(), DatePicker::make('end_date')->afterOrEqual('start_date'), Select::make('billing_cycle')->options(['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One-Time', 'custom' => 'Custom'])->required(), DatePicker::make('next_billing_date'), Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended', 'cancelled' => 'Cancelled'])->default('draft')->required(), Textarea::make('notes'), Repeater::make('items')->relationship()->schema([TextInput::make('description')->required()->maxLength(255), TextInput::make('quantity')->numeric()->minValue(0.0001)->step(0.0001)->default(1)->required(), TextInput::make('unit_price')->numeric()->minValue(0)->step(0.01)->required()->prefix('Rp'), DatePicker::make('effective_from')->required()->default(today()), Select::make('target_type')->options(['App\\Models\\PhysicalServer' => 'Physical Server', 'App\\Models\\ComputeInstance' => 'Compute Instance', 'App\\Models\\Service' => 'Service', 'App\\Models\\Domain' => 'Domain'])->live()->afterStateUpdated(fn ($set) => $set('target_id', null)), Select::make('target_id')->options(function (Get $get): array {
            $type = $get('target_type');

            return in_array($type, [PhysicalServer::class, ComputeInstance::class, Service::class, Domain::class], true) ? $type::query()->pluck($type === Domain::class ? 'fqdn' : 'name', 'id')->all() : [];
        })->searchable()->required(fn (Get $get) => filled($get('target_type')))])->columnSpanFull()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('number'), TextEntry::make('party.name'), TextEntry::make('direction'), TextEntry::make('start_date')->date(), TextEntry::make('end_date')->date(), TextEntry::make('billing_cycle'), TextEntry::make('next_billing_date')->date(), TextEntry::make('status'), RepeatableEntry::make('items')->schema([TextEntry::make('description'), TextEntry::make('quantity'), TextEntry::make('unit_price')->money('IDR')])->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('number')->searchable()->sortable(), TextColumn::make('party.name')->searchable(), TextColumn::make('direction')->badge(), TextColumn::make('status')->badge(), TextColumn::make('next_billing_date')->date()->sortable()])->filters([SelectFilter::make('direction')->options(['income' => 'Income', 'expense' => 'Expense']), SelectFilter::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended', 'cancelled' => 'Cancelled'])])->recordActions([ViewAction::make(), static::editContractAction(), Action::make('generateInvoice')->label('Generate Invoice')->visible(fn (BillingContract $record) => auth()->user()->can('update', $record) && $record->status === 'active')->schema([TextInput::make('number')->required()->default(fn () => 'INV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4))), DatePicker::make('issue_date')->required()->default(today()), DatePicker::make('due_date')->required()->default(today()->addDays(14))->afterOrEqual('issue_date'), DatePicker::make('period_start'), DatePicker::make('period_end')->afterOrEqual('period_start')])->action(function (BillingContract $record, array $data): void {
            app(GenerateInvoice::class)->execute($record, $data);
            Notification::make()->title('Draft invoice created')->success()->send();
        })]);
    }

    private static function editContractAction(): EditAction
    {
        return EditAction::make()
            ->label('Edit')
            ->button()
            ->schema([
                TextInput::make('number')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('party_id')->relationship('party', 'name')->searchable()->preload()->required(),
                Select::make('direction')->options(['income' => 'Income', 'expense' => 'Expense'])->required(),
                TextInput::make('amount')->numeric()->minValue(0)->required()->prefix('Rp'),
                TextInput::make('currency')->required()->regex('/^[A-Z]{3}$/'),
                Select::make('billing_cycle')->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One time'])->required(),
                DatePicker::make('starts_at')->required(),
                DatePicker::make('first_billing_date')->required()->afterOrEqual('starts_at'),
                DatePicker::make('ends_at')->afterOrEqual('starts_at'),
                TextInput::make('generate_days_before')->numeric()->minValue(0)->required(),
                TextInput::make('payment_terms_days')->numeric()->minValue(0)->required(),
                Toggle::make('auto_generate'),
                Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended', 'cancelled' => 'Cancelled'])->required(),
                Textarea::make('notes'),
            ])
            ->mutateRecordDataUsing(function (array $data): array {
                $data['starts_at'] ??= $data['start_date'] ?? null;
                $data['first_billing_date'] ??= $data['next_billing_date'] ?? $data['start_date'] ?? null;

                return $data;
            })
            ->mutateFormDataUsing(function (array $data): array {
                $data['start_date'] = $data['starts_at'];
                $data['end_date'] = $data['ends_at'] ?? null;

                return $data;
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageBillingContracts::route('/')];
    }
}
