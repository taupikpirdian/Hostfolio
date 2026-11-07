<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Resources\ServiceDeployments\ServiceDeploymentResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Service;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ServicesOverview extends Page
{
    protected string $view = 'filament.pages.section-overview';

    protected static ?string $title = 'Services';

    protected static ?string $navigationLabel = 'Services';

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('inventory') ?? false;
    }

    protected function getViewData(): array
    {
        $links = [
            ['title' => 'Hosted Services', 'description' => 'Daftar aplikasi dan layanan yang dikelola.', 'url' => ServiceResource::getUrl('index'), 'icon' => Heroicon::OutlinedGlobeAlt],
            ['title' => 'Deployments', 'description' => 'Penempatan service pada compute instance.', 'url' => ServiceDeploymentResource::getUrl('index'), 'icon' => Heroicon::OutlinedRectangleStack],
            ['title' => 'Domains', 'description' => 'Domain, registrar, dan tanggal kedaluwarsa.', 'url' => DomainResource::getUrl('index'), 'icon' => Heroicon::OutlinedGlobeAlt],
        ];
        if (auth()->user()?->canManage('inventory')) {
            $links[] = ['title' => 'Import Inventory', 'description' => 'Impor dan tinjau data dari CSV.', 'url' => ImportInventory::getUrl(), 'icon' => Heroicon::OutlinedArrowUpTray];
        }

        return [
            'title' => 'Services',
            'description' => 'Kelola layanan hosting, deployment, domain, dan data migrasi.',
            'stats' => [
                ['label' => 'Running Services', 'value' => number_format(Service::where('status', 'running')->count()), 'detail' => 'Sedang beroperasi'],
                ['label' => 'Archived Services', 'value' => number_format(Service::where('status', 'archived')->count()), 'detail' => 'Histori tetap tersimpan'],
                ['label' => 'All Services', 'value' => number_format(Service::count()), 'detail' => 'Semua status'],
            ],
            'links' => $links,
        ];
    }
}
