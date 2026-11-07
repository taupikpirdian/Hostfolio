<?php

namespace App\Filament\Resources\Payments;

use App\Actions\Billing\ReversePayment;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('invoice.number'), TextEntry::make('paid_at')->date(), TextEntry::make('amount')->money('IDR'), TextEntry::make('method'), TextEntry::make('reference'), TextEntry::make('notes'), TextEntry::make('reversed_at')->dateTime(), TextEntry::make('reversal_reason')]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->with('invoice'))->columns([TextColumn::make('invoice.number')->searchable(), TextColumn::make('paid_at')->date()->sortable(), TextColumn::make('amount')->money('IDR')->sortable(), TextColumn::make('method'), TextColumn::make('reference')->searchable(), TextColumn::make('reversed_at')->dateTime()])->filters([Filter::make('valid')->query(fn (Builder $query) => $query->whereNull('reversed_at')), Filter::make('reversed')->query(fn (Builder $query) => $query->whereNotNull('reversed_at'))])->recordActions([ViewAction::make(), Action::make('downloadProof')->label('Proof')->visible(fn (Payment $record) => filled($record->proof_path) && auth()->user()->can('view', $record))->url(fn (Payment $record) => URL::temporarySignedRoute('payments.proof', now()->addMinutes(5), ['payment' => $record->id]))->openUrlInNewTab(), Action::make('reverse')->color('danger')->requiresConfirmation()->visible(fn (Payment $record) => $record->reversed_at === null && auth()->user()->can('update', $record))->schema([Textarea::make('reason')->required()->minLength(3)])->action(function (Payment $record, array $data): void {
            app(ReversePayment::class)->execute($record, $data['reason']);
            Notification::make()->title('Payment reversed; history retained')->success()->send();
        })]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePayments::route('/')];
    }
}
