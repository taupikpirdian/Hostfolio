<?php

namespace App\Providers\Filament;

use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ServersOverview;
use App\Filament\Pages\SettingsOverview;
use App\Filament\Resources\BillingContracts\BillingContractResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\ApplicationSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->brandName(fn (): string => ApplicationSetting::where('key', 'organization_name')->value('value') ?: config('app.name'))
            ->brandLogo(fn () => new HtmlString(view('filament.brand')->render()))
            ->brandLogoHeight('2.75rem')
            ->databaseTransactions()
            ->path('')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->groups([
                NavigationGroup::make('Workspace')->items([
                    NavigationItem::make('Dashboard')->icon(Heroicon::OutlinedSquares2x2)->url(fn (): string => Dashboard::getUrl())->activeWhen(fn (): bool => request()->routeIs(Dashboard::getRouteName()))->sort(1),
                    NavigationItem::make('Servers')->icon(Heroicon::OutlinedServerStack)->url(fn (): string => ServersOverview::getUrl())->activeWhen(fn (): bool => request()->routeIs(ServersOverview::getRouteName(), 'filament.admin.resources.physical-servers.*', 'filament.admin.resources.compute-instances.*', 'filament.admin.resources.providers.*'))->visible(fn (): bool => auth()->user()?->canView('inventory') ?? false)->sort(2),
                    NavigationItem::make('Billing')
                        ->icon(Heroicon::OutlinedCreditCard)
                        ->url(fn (): string => BillingOverview::getUrl())
                        ->activeWhen(fn (): bool => request()->routeIs(BillingOverview::getRouteName(), 'filament.admin.pages.reports'))
                        ->visible(fn (): bool => auth()->user()?->canView('billing') ?? false)
                        ->childItems([
                            NavigationItem::make('Contracts')->url(fn (): string => BillingContractResource::getUrl('index'))->activeWhen(fn (): bool => request()->routeIs('filament.admin.resources.billing-contracts.*')),
                            NavigationItem::make('Invoices & Bills')->url(fn (): string => InvoiceResource::getUrl('index'))->activeWhen(fn (): bool => request()->routeIs('filament.admin.resources.invoices.*')),
                            NavigationItem::make('Payments')->url(fn (): string => PaymentResource::getUrl('index'))->activeWhen(fn (): bool => request()->routeIs('filament.admin.resources.payments.*')),
                            NavigationItem::make('Clients & Vendors')->url(fn (): string => PartyResource::getUrl('index'))->activeWhen(fn (): bool => request()->routeIs('filament.admin.resources.parties.*')),
                        ])
                        ->sort(4),
                ]),
                NavigationGroup::make('Configuration')->items([
                    NavigationItem::make('Settings')->icon(Heroicon::OutlinedCog6Tooth)->url(fn (): string => SettingsOverview::getUrl())->activeWhen(fn (): bool => request()->routeIs(SettingsOverview::getRouteName(), 'filament.admin.resources.application-settings.*', 'filament.admin.resources.audit-logs.*', 'filament.admin.resources.roles.*', 'filament.admin.resources.users.*', 'filament.admin.resources.categories.*'))->visible(fn (): bool => auth()->user()?->canView('settings') ?? false)->sort(1),
                ]),
            ]))
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.sidebar-footer'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
