<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Services\DashboardService;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingOverview extends Reports
{
    protected string $view = 'filament.pages.billing-overview';

    protected static ?string $title = 'Billing';

    protected static ?string $navigationLabel = 'Billing';

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public string $report = 'income';

    public function getViewData(): array
    {
        $data = parent::getViewData();
        $to = $this->to ? Carbon::parse($this->to) : now();
        $from = $this->from ? Carbon::parse($this->from) : $to->copy()->startOfMonth()->subMonths(11);
        if ($from->gt($to)) {
            $to = $from->copy();
        }
        if ($from->diffInMonths($to) > 23) {
            $from = $to->copy()->startOfMonth()->subMonths(23);
        }
        $unpaidCount = Invoice::query()->where('status', 'issued')->where('type', 'receivable')
            ->whereRaw('total > (select COALESCE(SUM(amount), 0) from payments where payments.invoice_id = invoices.id and payments.reversed_at is null)')
            ->count();

        return [
            ...$data,
            'unpaidInvoiceCount' => $unpaidCount,
            'upcomingRenewals' => DB::table('billing_contracts')->where('status', 'active')
                ->whereBetween('next_billing_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->count(),
            'cashflowMonths' => app(DashboardService::class)->monthlyCashflow($from, $to),
            'chartFrom' => $from,
            'chartTo' => $to,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('billing') ?? false;
    }
}
