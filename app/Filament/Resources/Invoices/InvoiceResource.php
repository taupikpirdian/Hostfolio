<?php

namespace App\Filament\Resources\Invoices;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Billing\VoidInvoice;
use App\Filament\Resources\Invoices\Pages\ManageInvoices;
use App\Models\ComputeInstance;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\PhysicalServer;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'draft' && parent::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')->relationship('category', 'name')->searchable()->preload()->label('Expense category'), Select::make('source_currency')->options(['IDR' => 'IDR', 'USD' => 'USD', 'EUR' => 'EUR', 'SGD' => 'SGD', 'MYR' => 'MYR'])->default('IDR')->required()->live(), TextInput::make('source_amount')->numeric()->minValue(0.01)->label('Original amount')->helperText('Invoice items and totals are always recorded in IDR.'), TextInput::make('exchange_rate')->numeric()->minValue(0.000001)->label('Manual rate to IDR'), TextInput::make('exchange_rate_source')->maxLength(255)->label('Rate source / date'), TextInput::make('number')->required()->unique(ignoreRecord: true)->maxLength(255), Select::make('type')->options(['receivable' => 'Customer Invoice', 'payable' => 'Vendor Bill'])->required(), Select::make('party_id')->relationship('party', 'name')->searchable()->preload()->required(), Select::make('billing_contract_id')->relationship('contract', 'number')->searchable()->preload(), DatePicker::make('issue_date')->required()->default(today()), DatePicker::make('due_date')->required()->afterOrEqual('issue_date'), DatePicker::make('period_start'), DatePicker::make('period_end')->afterOrEqual('period_start'), Textarea::make('notes'), Repeater::make('items')->relationship()->minItems(1)->schema([TextInput::make('description')->required()->maxLength(255), TextInput::make('quantity')->required()->numeric()->minValue(0.0001)->step(0.0001)->default(1), TextInput::make('unit_price')->required()->numeric()->minValue(0)->step(0.01)->prefix('Rp'), TextInput::make('discount')->required()->numeric()->minValue(0)->step(0.01)->default(0)->prefix('Rp'), TextInput::make('tax')->required()->numeric()->minValue(0)->step(0.01)->default(0)->prefix('Rp'),
                Select::make('target_type')->label('Related inventory type')->options([PhysicalServer::class => 'Physical Server', ComputeInstance::class => 'Compute Instance', Service::class => 'Service', Domain::class => 'Domain'])->live()->afterStateUpdated(fn (Set $set) => $set('target_id', null)),
                Select::make('target_id')->label('Related inventory')->options(function (Get $get): array {
                    $type = $get('target_type');

                    return in_array($type, [PhysicalServer::class, ComputeInstance::class, Service::class, Domain::class], true) ? $type::query()->pluck($type === Domain::class ? 'fqdn' : 'name', 'id')->all() : [];
                })->searchable()->required(fn (Get $get) => filled($get('target_type')))->rules(function (Get $get): array {
                    $type = $get('target_type');

                    return in_array($type, [PhysicalServer::class, ComputeInstance::class, Service::class, Domain::class], true) ? [Rule::exists((new $type)->getTable(), 'id')] : [];
                })])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('category.name'), TextEntry::make('source_currency'), TextEntry::make('source_amount'), TextEntry::make('exchange_rate'), TextEntry::make('exchange_rate_source'), TextEntry::make('number'), TextEntry::make('type'), TextEntry::make('party.name'), TextEntry::make('contract.number'), TextEntry::make('status')->badge(), TextEntry::make('payment_status')->badge(), TextEntry::make('issue_date')->date(), TextEntry::make('due_date')->date(), TextEntry::make('total')->money('IDR'), TextEntry::make('paid_total')->money('IDR'), TextEntry::make('outstanding')->money('IDR'), TextEntry::make('notes'), RepeatableEntry::make('items')->schema([TextEntry::make('description'), TextEntry::make('quantity'), TextEntry::make('unit_price')->money('IDR'), TextEntry::make('discount')->money('IDR'), TextEntry::make('tax')->money('IDR'), TextEntry::make('total')->money('IDR')])->columnSpanFull(), RepeatableEntry::make('payments')->schema([TextEntry::make('paid_at')->date(), TextEntry::make('amount')->money('IDR'), TextEntry::make('method'), TextEntry::make('reference'), TextEntry::make('reversed_at')->dateTime(), TextEntry::make('reversal_reason')])->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->with(['party', 'payments']))->columns([TextColumn::make('number')->searchable()->sortable(), TextColumn::make('type')->badge(), TextColumn::make('party.name')->searchable(), TextColumn::make('status')->badge(), TextColumn::make('total')->money('IDR')->sortable(), TextColumn::make('outstanding')->money('IDR'), TextColumn::make('payment_status')->badge(), TextColumn::make('due_date')->date()->sortable()->color(fn (Invoice $record) => $record->is_overdue ? 'danger' : null)])->filters([Filter::make('overdue')->query(fn (Builder $query) => $query->where('status', 'issued')->whereDate('due_date', '<', today())->whereRaw('total > COALESCE((SELECT SUM(amount) FROM payments WHERE payments.invoice_id = invoices.id AND reversed_at IS NULL), 0)')), SelectFilter::make('type')->options(['receivable' => 'Customer Invoice', 'payable' => 'Vendor Bill']), SelectFilter::make('status')->options(['draft' => 'Draft', 'issued' => 'Issued', 'void' => 'Void'])])->recordActions([ViewAction::make(), EditAction::make()->visible(fn (Invoice $record) => $record->status === 'draft' && auth()->user()->can('update', $record)), Action::make('issue')->requiresConfirmation()->visible(fn (Invoice $record) => $record->status === 'draft' && auth()->user()->can('update', $record))->action(function (Invoice $record): void {
            app(IssueInvoice::class)->execute($record);
            Notification::make()->title('Invoice issued')->success()->send();
        }), Action::make('recordPayment')->label('Record Payment')->visible(fn (Invoice $record) => $record->status === 'issued' && bccomp($record->outstanding, '0', 2) > 0 && auth()->user()->can('update', $record))->schema([DatePicker::make('paid_at')->required()->default(today()), TextInput::make('amount')->required()->numeric()->minValue(0.01)->step(0.01)->prefix('Rp'), Select::make('method')->required()->options(['transfer' => 'Bank Transfer', 'cash' => 'Cash', 'card' => 'Card', 'other' => 'Other']), TextInput::make('reference')->maxLength(255), FileUpload::make('proof_path')->disk('local')->directory('payment-proofs')->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120), Textarea::make('notes')])->action(function (Invoice $record, array $data): void {
            app(RecordPayment::class)->execute($record, $data);
            Notification::make()->title('Payment recorded')->success()->send();
        }), Action::make('void')->color('danger')->requiresConfirmation()->visible(fn (Invoice $record) => $record->status !== 'void' && bccomp($record->paid_total, '0', 2) === 0 && auth()->user()->can('update', $record))->action(function (Invoice $record): void {
            app(VoidInvoice::class)->execute($record);
            Notification::make()->title('Invoice voided')->success()->send();
        })]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvoices::route('/')];
    }
}
