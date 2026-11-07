<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use Filament\Pages\Page;

class Reports extends Page
{
    protected string $view = 'filament.pages.reports';

    protected static ?string $title = 'Reports & Renewals';

    public string $report = 'inventory';

    public ?string $from = null;

    public ?string $to = null;

    public int $days = 30;

    public int $pageNumber = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('inventory') || auth()->user()?->canView('billing');
    }

    public function updated(string $property): void
    {
        if ($property !== 'pageNumber') {
            $this->pageNumber = 1;
        }
    }

    public function previousPage(): void
    {
        $this->pageNumber = max(1, $this->pageNumber - 1);
    }

    public function nextPage(): void
    {
        $this->pageNumber++;
    }

    public function getViewData(): array
    {
        $inventory = ['inventory' => 'Inventory', 'resources' => 'Resource allocation'];
        $billing = ['income' => 'Income invoices', 'expenses' => 'Vendor bills / expenses', 'cashflow' => 'Cashflow by payment date', 'aging' => 'AR / AP Aging', 'unpaid' => 'Unpaid invoices', 'overdue' => 'Overdue invoices', 'income_by_service' => 'Income by service', 'expense_by_provider' => 'Expense by provider', 'renewals' => 'Renewal schedule'];
        $choices = [...(auth()->user()->canView('inventory') ? $inventory : []), ...(auth()->user()->canView('billing') ? $billing : [])];
        if (! isset($choices[$this->report])) {
            $this->report = array_key_first($choices);
        }
        $this->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'days' => 'required|integer|in:0,7,14,30,60']);
        $service = app(ReportService::class);
        $rows = $service->rows($this->report, $this->from ?: null, $this->to ?: null, $this->days, $this->pageNumber);
        if ($this->report === 'renewals' && $this->days === 0) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['Days remaining'] < 0));
        }

        return ['choices' => $choices, 'total' => $service->totalRows, 'rows' => $rows, 'summary' => auth()->user()->canView('billing') ? $service->financialSummary($this->from ?: null, $this->to ?: null) : []];
    }
}
