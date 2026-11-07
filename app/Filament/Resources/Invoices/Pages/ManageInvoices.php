<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageInvoices extends ManageRecords
{
    private const PAID_TOTAL_SQL = 'COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.invoice_id = invoices.id AND payments.reversed_at IS NULL), 0)';

    protected static string $resource = InvoiceResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'unpaid' => Tab::make('Unpaid')->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('invoices.status', 'issued')
                ->where(fn (Builder $query): Builder => $query
                    ->whereRaw('invoices.total > 0 AND invoices.total > '.self::PAID_TOTAL_SQL)
                    ->orWhere(fn (Builder $query): Builder => $query
                        ->where('invoices.total', 0)
                        ->whereDoesntHave('payments', fn (Builder $payments): Builder => $payments->whereNull('reversed_at')->where('method', 'zero_amount'))))),
            'paid' => Tab::make('Paid')->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('invoices.status', 'issued')
                ->where(fn (Builder $query): Builder => $query
                    ->whereRaw('invoices.total > 0 AND invoices.total <= '.self::PAID_TOTAL_SQL)
                    ->orWhere(fn (Builder $query): Builder => $query
                        ->where('invoices.total', 0)
                        ->whereHas('payments', fn (Builder $payments): Builder => $payments->whereNull('reversed_at')->where('method', 'zero_amount'))))),
            'void' => Tab::make('Void')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('invoices.status', 'void')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
