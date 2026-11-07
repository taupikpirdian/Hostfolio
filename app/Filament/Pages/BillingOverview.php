<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BillingContracts\BillingContractResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Support\Icons\Heroicon;

class BillingOverview extends Reports
{
    protected static ?string $title = 'Billing';

    protected static ?string $navigationLabel = 'Billing';

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public string $report = 'income';

    public function getViewData(): array
    {
        return [
            ...parent::getViewData(),
            'billingLinks' => [
                ['title' => 'Contracts', 'url' => BillingContractResource::getUrl('index')],
                ['title' => 'Invoices & Bills', 'url' => InvoiceResource::getUrl('index')],
                ['title' => 'Payments', 'url' => PaymentResource::getUrl('index')],
                ['title' => 'Clients & Vendors', 'url' => PartyResource::getUrl('index')],
                ['title' => 'Renewals', 'url' => Renewals::getUrl()],
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('billing') ?? false;
    }
}
