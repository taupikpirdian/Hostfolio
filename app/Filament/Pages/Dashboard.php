<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Services\DashboardService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithPagination;

class Dashboard extends BaseDashboard
{
    use WithPagination;

    protected string $view = 'filament.pages.dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $period = 'year';

    public string $serviceSearch = '';

    public string $serviceStatus = 'all';

    public function getWidgets(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['serviceSearch', 'serviceStatus'], true)) {
            $this->resetPage();
        }
    }

    protected function getViewData(): array
    {
        $period = in_array($this->period, ['year', 'last_12_months'], true) ? $this->period : 'year';

        return [
            ...app(DashboardService::class)->data($period, trim($this->serviceSearch), $this->serviceStatus, $this->getPage()),
            'invoiceUrl' => InvoiceResource::getUrl('index'),
            'serviceUrl' => ServiceResource::getUrl('index'),
            'serversUrl' => ServersOverview::getUrl(),
            'billingUrl' => BillingOverview::getUrl(),
        ];
    }
}
