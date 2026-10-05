<?php

namespace Modules\EgyptTax\Console;

use Illuminate\Console\Command;
use Modules\EgyptTax\Actions\SubmitReceipts;

class SubmitCommand extends Command
{
    protected $signature = 'egypttax:submit {--device= : Only this ETA device id}';

    protected $description = 'Send waiting E-Receipts to the Egyptian Tax Authority';

    public function handle(SubmitReceipts $submit): int
    {
        $accepted = $submit->handle($this->option('device') ? (int) $this->option('device') : null);
        $this->info(__('egypttax::receipts.sent', ['count' => $accepted]));

        return self::SUCCESS;
    }
}
