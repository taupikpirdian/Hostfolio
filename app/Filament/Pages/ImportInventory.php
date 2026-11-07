<?php

namespace App\Filament\Pages;

use App\Actions\Inventory\ConfirmInventoryImport;
use App\Actions\Inventory\StageInventoryImport;
use App\Models\ComputeInstance;
use App\Models\ImportBatch;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ImportInventory extends Page
{
    use WithFileUploads, WithPagination;

    protected string $view = 'filament.pages.import-inventory';

    protected static ?string $title = 'Import Inventory';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public $csv;

    public array $headers = [];

    public array $mapping = ['name' => 'Service', 'status' => 'Status', 'reference' => 'No', 'server' => 'Server', 'vcpu' => 'Core', 'ram_mb' => 'RAM', 'storage_gb' => 'Storage', 'start_date' => 'Date Start', 'end_date' => 'Date End', 'amount' => 'Amount'];

    public array $instances = [];

    #[Locked]
    public ?int $batchId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('inventory') ?? false;
    }

    public function updatedCsv(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['csv' => 'required|file|max:10240|extensions:csv,txt']);
        $stream = fopen($this->csv->getRealPath(), 'r');
        $headers = fgetcsv($stream, 0, ',', '"', '');
        fclose($stream);
        $this->headers = array_map(fn ($v) => preg_replace('/^\xEF\xBB\xBF/', '', (string) $v), $headers ?: []);
        $this->batchId = null;
    }

    public function preview(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['csv' => 'required|file|max:10240|extensions:csv,txt']);
        $batch = app(StageInventoryImport::class)->execute(file_get_contents($this->csv->getRealPath()), $this->mapping);
        $this->batchId = $batch->id;
        $this->resetPage('importPage');
    }

    public function confirm(): void
    {
        abort_unless(static::canAccess(), 403);
        app(ConfirmInventoryImport::class)->execute(ImportBatch::findOrFail($this->batchId), $this->instances);
        Notification::make()->title('Import dikonfirmasi')->success()->send();
    }

    public function getViewData(): array
    {
        return ['batch' => $this->batchId ? ImportBatch::find($this->batchId) : null, 'rows' => $this->batchId ? ImportBatch::findOrFail($this->batchId)->rows()->orderBy('row_number')->paginate(25, pageName: 'importPage') : null, 'computeInstances' => ComputeInstance::orderBy('name')->pluck('name', 'id'), 'batches' => ImportBatch::latest()->limit(15)->get()];
    }

    public function resume(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->batchId = ImportBatch::findOrFail($id)->id;
        $this->resetPage('importPage');
    }
}
