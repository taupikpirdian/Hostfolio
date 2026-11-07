<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ComputeInstances\ComputeInstanceResource;
use App\Filament\Resources\PhysicalServers\PhysicalServerResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Models\ComputeInstance;
use App\Models\PhysicalServer;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ServersOverview extends Page
{
    protected string $view = 'filament.pages.section-overview';

    protected static ?string $title = 'Servers';

    protected static ?string $navigationLabel = 'Servers';

    protected static string|\UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

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
        return [
            'title' => 'Servers',
            'description' => 'Kelola server fisik, compute instance, kapasitas, dan provider infrastruktur.',
            'stats' => [
                ['label' => 'Physical Servers', 'value' => number_format(PhysicalServer::count()), 'detail' => 'Mesin fisik'],
                ['label' => 'Compute Instances', 'value' => number_format(ComputeInstance::count()), 'detail' => 'VM, LXC, Docker, VPS'],
                ['label' => 'Active Hosts', 'value' => number_format(PhysicalServer::where('status', 'active')->count()), 'detail' => 'Status aktif'],
            ],
            'links' => [
                ['title' => 'Physical Servers', 'description' => 'Data hardware, kapasitas, lokasi, dan hypervisor.', 'url' => PhysicalServerResource::getUrl('index'), 'icon' => Heroicon::OutlinedServerStack],
                ['title' => 'Compute Instances', 'description' => 'VM, LXC, container, VPS, dan shared hosting.', 'url' => ComputeInstanceResource::getUrl('index'), 'icon' => Heroicon::OutlinedCpuChip],
                ['title' => 'Providers', 'description' => 'Kelola provider hardware dan hosting.', 'url' => ProviderResource::getUrl('index'), 'icon' => Heroicon::OutlinedBuildingOffice2],
            ],
        ];
    }
}
