<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Observers\AuditObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\BillingPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\SettingsPolicy;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TrustProxies::at(config('app.trusted_proxies', []));
        foreach (['Provider', 'PhysicalServer', 'ComputeInstance', 'ResourceAllocation', 'Service', 'ServiceDeployment', 'Domain', 'ImportBatch', 'ImportRow'] as $name) {
            $class = 'App\\Models\\'.$name;
            if (class_exists($class)) {
                Gate::policy($class, InventoryPolicy::class);
                $class::observe(AuditObserver::class);
            }
        }
        foreach (['Party', 'BillingContract', 'ContractItem', 'Invoice', 'InvoiceItem', 'Payment'] as $name) {
            $class = 'App\\Models\\'.$name;
            if (class_exists($class)) {
                Gate::policy($class, BillingPolicy::class);
                $class::observe(AuditObserver::class);
            }
        }
        foreach (['User', 'Role', 'ApplicationSetting', 'Category'] as $name) {
            $class = 'App\\Models\\'.$name;
            Gate::policy($class, SettingsPolicy::class);
            $class::observe(AuditObserver::class);
        }
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
