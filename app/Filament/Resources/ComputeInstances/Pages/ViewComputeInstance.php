<?php

namespace App\Filament\Resources\ComputeInstances\Pages;

use App\Actions\Billing\CreateBillingContract;
use App\Actions\Billing\GenerateInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Inventory\DeployService;
use App\Filament\Resources\ComputeInstances\ComputeInstanceResource;
use App\Models\BillingContract;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Service;
use App\Services\MinioStorage;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ViewComputeInstance extends ViewRecord
{
    protected static string $resource = ComputeInstanceResource::class;

    protected string $view = 'filament.resources.compute-instances.view';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createService')
                ->label('Tambah Service')
                ->visible(fn (): bool => auth()->user()?->canManage('inventory') ?? false)
                ->schema([
                    TextInput::make('name')->label('Nama Service')->required()->maxLength(255),
                    Select::make('type')->options(['website' => 'Website', 'api' => 'API', 'database' => 'Database', 'worker' => 'Worker', 'internal_application' => 'Internal Application', 'other' => 'Other'])->required(),
                    Select::make('status')->options(['draft' => 'Draft', 'running' => 'Running', 'stopped' => 'Stopped'])->default('running')->required(),
                    Select::make('party_id')->label('Client')->options(fn (): array => Party::query()->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    Textarea::make('description'),
                    Select::make('environment')->options(['production' => 'Production', 'staging' => 'Staging', 'development' => 'Development'])->default('production')->required(),
                ])
                ->action(function (array $data): void {
                    Gate::authorize('create', Service::class);
                    DB::transaction(function () use ($data): void {
                        $service = Service::create(collect($data)->only(['name', 'type', 'status', 'party_id', 'description'])->all());
                        app(DeployService::class)->execute($service, $this->getRecord(), ['environment' => $data['environment']]);
                    });
                }),
            Action::make('createBillingContract')->label('Buat Billing Contract')->visible(fn (): bool => auth()->user()?->canManage('billing') ?? false)->schema([
                TextInput::make('number')->default(fn (): string => 'CTR-'.now()->format('YmdHis'))->required(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('direction')->options(['income' => 'Income', 'expense' => 'Expense'])->live()->required(),
                Select::make('party_id')->label(fn (Get $get): string => $get('direction') === 'expense' ? 'Vendor' : 'Client')->options(fn (Get $get): array => Party::query()->when(filled($get('direction')), fn ($query) => $query->whereIn('type', [$get('direction') === 'income' ? 'client' : 'vendor', 'both']))->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                TextInput::make('amount')->numeric()->minValue(0)->required(),
                TextInput::make('currency')->default('IDR')->required()->regex('/^[A-Z]{3}$/'),
                Select::make('billing_cycle')->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One time'])->required(),
                DatePicker::make('starts_at')->default(today())->required(), DatePicker::make('first_billing_date')->default(today())->required(), DatePicker::make('ends_at')->afterOrEqual('starts_at'),
                TextInput::make('generate_days_before')->numeric()->default(14)->required(), TextInput::make('payment_terms_days')->numeric()->default(0)->required(),
                Toggle::make('auto_generate')->default(true), Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'])->default('active')->required(), Textarea::make('notes'),
            ])->action(fn (array $data) => app(CreateBillingContract::class)->execute($this->getRecord(), $data)),
            Action::make('editBillingContract')->label('Edit Billing Contract')->visible(fn (): bool => auth()->user()?->canManage('billing') ?? false)
                ->fillForm(function (array $arguments): array {
                    $contract = $this->billingContractsForInstance()->findOrFail($arguments['contract_id']);
                    Gate::authorize('update', $contract);

                    return [
                        'name' => $contract->name,
                        'party_id' => $contract->party_id,
                        'direction' => $contract->direction,
                        'amount' => $contract->amount,
                        'currency' => $contract->currency,
                        'billing_cycle' => $contract->billing_cycle,
                        'starts_at' => $contract->starts_at?->toDateString() ?? $contract->start_date?->toDateString(),
                        'first_billing_date' => $contract->first_billing_date?->toDateString() ?? $contract->next_billing_date?->toDateString(),
                        'ends_at' => $contract->ends_at?->toDateString(),
                        'generate_days_before' => $contract->generate_days_before,
                        'payment_terms_days' => $contract->payment_terms_days,
                        'auto_generate' => $contract->auto_generate,
                        'status' => $contract->status,
                        'notes' => $contract->notes,
                    ];
                })->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('party_id')->options(fn (): array => Party::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                    Select::make('direction')->options(['income' => 'Income', 'expense' => 'Expense'])->required(),
                    TextInput::make('amount')->numeric()->minValue(0)->required(),
                    TextInput::make('currency')->required()->regex('/^[A-Z]{3}$/'),
                    Select::make('billing_cycle')->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One time'])->required(),
                    DatePicker::make('starts_at')->required(),
                    DatePicker::make('first_billing_date')->required()->afterOrEqual('starts_at'),
                    DatePicker::make('ends_at')->afterOrEqual('starts_at'),
                    TextInput::make('generate_days_before')->numeric()->minValue(0)->required(),
                    TextInput::make('payment_terms_days')->numeric()->minValue(0)->required(),
                    Toggle::make('auto_generate'),
                    Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'])->required(),
                    Textarea::make('notes'),
                ])->action(function (array $arguments, array $data): void {
                    $contract = $this->billingContractsForInstance()->findOrFail($arguments['contract_id']);
                    Gate::authorize('update', $contract);
                    $contract->fill($data);
                    $contract->start_date = $data['starts_at'];
                    $contract->end_date = $data['ends_at'] ?? null;
                    if ($contract->billing_sequence === 0) {
                        $contract->next_billing_date = $data['first_billing_date'];
                    }
                    $contract->save();
                }),
            Action::make('viewContractInvoices')->label('Lihat Invoice')->visible(fn (): bool => auth()->user()?->canView('billing') ?? false)
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(function (array $arguments): View {
                    $contract = $this->billingContractsForInstance()->with('invoices.payments')->findOrFail($arguments['contract_id']);
                    Gate::authorize('view', $contract);

                    return view('filament.resources.compute-instances.contract-invoices-modal', ['contract' => $contract]);
                }),
            Action::make('generateManualInvoice')->label('Buat Invoice')->visible(fn (): bool => auth()->user()?->canManage('billing') ?? false)->schema([
                Select::make('billing_contract_id')->label('Billing Contract')->options(fn (): array => $this->getRecord()->billingContracts()->orderBy('number')->pluck('name', 'id')->all())->live()->required(),
                TextInput::make('number')->default(fn (): string => 'INV-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4)))->required(),
                DatePicker::make('issue_date')->default(today())->required(),
                DatePicker::make('period_start')->default(fn (Get $get) => BillingContract::find($get('billing_contract_id'))?->next_billing_date?->toDateString() ?? today()->toDateString())->required(),
                DatePicker::make('period_end')->default(fn (Get $get) => BillingContract::find($get('billing_contract_id'))?->next_billing_date?->toDateString() ?? today()->toDateString())->required(),
                DatePicker::make('due_date')->default(fn (Get $get) => today()->addDays((int) (BillingContract::find($get('billing_contract_id'))?->payment_terms_days ?? 0)))->required(),
            ])->action(function (array $data): void {
                $contract = $this->getRecord()->billingContracts()->findOrFail($data['billing_contract_id']);
                app(GenerateInvoice::class)->execute($contract, $data);
            }),
            Action::make('generateContractInvoicePdf')->label('Generate Invoice Contract')->icon('heroicon-o-document-text')->visible(fn (): bool => auth()->user()?->canView('billing') ?? false)->schema([
                Select::make('billing_contract_ids')->label('Billing Contracts')->multiple()->options(fn (): array => $this->billingContractsForInstance()->orderBy('number')->get()->mapWithKeys(fn (BillingContract $contract): array => [$contract->id => $contract->number.' — '.$contract->name])->all())->searchable()->required(),
                TextInput::make('period_month')->label('Periode Bulan')->type('month')->helperText('Kosongkan untuk semua periode.'),
            ])->action(fn (array $data) => redirect()->route('invoices.contract-unpaid-pdf', [
                'computeInstance' => $this->getRecord(),
                'contracts' => $data['billing_contract_ids'],
                ...array_filter(['period_month' => $data['period_month'] ?? null], fn ($value): bool => filled($value)),
            ])),
            Action::make('recordInvoicePayment')->label('Bayar Invoice')->visible(fn (): bool => auth()->user()?->canManage('billing') ?? false)->fillForm(fn (array $arguments): array => ['invoice_id' => $arguments['invoice_id'] ?? null])->schema([
                Select::make('invoice_id')->label('Invoice terbit')->options(fn (): array => $this->invoicesForInstance()->where('status', 'issued')->with('party')->get()->filter(fn (Invoice $invoice) => bccomp($invoice->outstanding, '0', 2) > 0)->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => $invoice->number.' · '.$invoice->party->name.' · '.$invoice->currency.' '.$invoice->outstanding])->all())->required(),
                DatePicker::make('paid_at')->default(today())->required(),
                TextInput::make('amount')->numeric()->minValue(.01)->required(),
                Select::make('method')->options(['transfer' => 'Transfer bank', 'cash' => 'Tunai', 'card' => 'Kartu', 'other' => 'Lainnya'])->required(),
                TextInput::make('reference')->maxLength(255),
                FileUpload::make('proof_path')->label('Bukti pembayaran')->disk('minio')->directory('payment-proofs')->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)->required(),
                Textarea::make('notes'),
            ])->action(function (array $data, MinioStorage $minio): void {
                abort_unless($minio->exists($data['proof_path']), 422, 'File bukti tidak ditemukan di MinIO.');
                $invoice = $this->invoicesForInstance()->findOrFail($data['invoice_id']);
                app(RecordPayment::class)->execute($invoice, $data);
            }),
        ];
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.compute-instances.header', ['actions' => $this->headerActionsWithoutService()]);
    }

    public function headerActionsWithoutService(): array
    {
        return array_values(array_filter($this->getCachedHeaderActions(), fn (Action $action): bool => ! in_array($action->getName(), ['createService', 'createBillingContract', 'editBillingContract', 'viewContractInvoices', 'generateManualInvoice', 'generateContractInvoicePdf', 'recordInvoicePayment'], true)));
    }

    public function serviceTabActions(): array
    {
        return array_values(array_filter($this->getCachedHeaderActions(), fn (Action $action): bool => $action->getName() === 'createService'));
    }

    public function billingTabActions(): array
    {
        return array_values(array_filter($this->getCachedHeaderActions(), fn (Action $action): bool => in_array($action->getName(), ['createBillingContract', 'generateManualInvoice', 'generateContractInvoicePdf'], true)));
    }

    public function removeServiceDeployment(int $deploymentId): void
    {
        $deployment = $this->getRecord()->deployments()->findOrFail($deploymentId);
        Gate::authorize('update', $deployment);
        if ($deployment->removed_at === null) {
            $deployment->update(['removed_at' => now()]);
        }
    }

    public function deleteDraftInvoice(int $invoiceId): void
    {
        $invoice = $this->invoicesForInstance()->findOrFail($invoiceId);
        $invoice->deleteDraft();
    }

    public function issueDraftInvoice(int $invoiceId): void
    {
        $invoice = $this->invoicesForInstance()->findOrFail($invoiceId);
        app(IssueInvoice::class)->execute($invoice);
    }

    public function cancelInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId): void {
            $invoice = $this->invoicesForInstance()->lockForUpdate()->findOrFail($invoiceId);
            abort_if($invoice->status === 'void', 422, 'Invoice ini sudah dibatalkan.');
            abort_if($invoice->payments()->whereNull('reversed_at')->exists(), 422, 'Invoice dengan pembayaran tidak dapat dibatalkan.');
            $invoice->transitionTo('void');
        });
    }

    private function invoicesForInstance(): Builder
    {
        $instance = $this->getRecord();
        $contractIds = $instance->billingContracts()->select('id');

        return Invoice::query()->whereIn('billing_contract_id', $contractIds);
    }

    private function billingContractsForInstance(): Builder
    {
        $instance = $this->getRecord();

        return BillingContract::query()->where(function (Builder $query) use ($instance): void {
            $query->where('compute_instance_id', $instance->id)
                ->orWhereIn('service_id', $instance->deployments()->select('service_id'));
        });
    }
}
