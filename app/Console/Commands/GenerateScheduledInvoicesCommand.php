<?php

namespace App\Console\Commands;

use App\Actions\Billing\GenerateScheduledInvoices;
use Illuminate\Console\Command;

class GenerateScheduledInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Create draft invoices for due active billing contracts';

    public function handle(GenerateScheduledInvoices $generator): int
    {
        $result = $generator->run();
        $this->info("Created {$result['created']} draft invoice(s).");
        foreach ($result['backlogs'] as $number) {
            $this->warn("Backlog cap reached for contract {$number}; rerun to continue catch-up.");
        }

        return self::SUCCESS;
    }
}
