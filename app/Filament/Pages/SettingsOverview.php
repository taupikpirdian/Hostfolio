<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ApplicationSettings\ApplicationSettingResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class SettingsOverview extends Page
{
    protected string $view = 'filament.pages.section-overview';

    protected static ?string $title = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canView('settings') ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'title' => 'Settings',
            'description' => 'Atur workspace, pengguna, role, kategori, dan histori aktivitas.',
            'stats' => [
                ['label' => 'Users', 'value' => number_format(User::count()), 'detail' => 'Akun workspace'],
                ['label' => 'Active Admins', 'value' => number_format(User::where('role', 'admin')->where('is_active', true)->count()), 'detail' => 'Administrator aktif'],
            ],
            'links' => [
                ['title' => 'Application Settings', 'description' => 'Identitas dan pengaturan workspace.', 'url' => ApplicationSettingResource::getUrl('index'), 'icon' => Heroicon::OutlinedCog6Tooth],
                ['title' => 'Users', 'description' => 'Akun pengguna dan akses panel.', 'url' => UserResource::getUrl('index'), 'icon' => Heroicon::OutlinedUsers],
                ['title' => 'Roles & Permissions', 'description' => 'Atur peran dan hak akses.', 'url' => RoleResource::getUrl('index'), 'icon' => Heroicon::OutlinedKey],
                ['title' => 'Categories', 'description' => 'Kategori invoice dan transaksi.', 'url' => CategoryResource::getUrl('index'), 'icon' => Heroicon::OutlinedTag],
                ['title' => 'Audit Logs', 'description' => 'Histori perubahan data penting.', 'url' => AuditLogResource::getUrl('index'), 'icon' => Heroicon::OutlinedClipboardDocumentList],
            ],
        ];
    }
}
