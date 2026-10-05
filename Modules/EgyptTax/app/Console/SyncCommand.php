<?php

namespace Modules\EgyptTax\Console;

use Illuminate\Console\Command;
use Modules\EgyptTax\Actions\SyncReceipts;

class SyncCommand extends Command
{
    protected $signature = 'egypttax:sync';

    protected $description = 'Read the validation status of submitted E-Receipts';

    public function handle(SyncReceipts $sync): int
    {
        $this->info(__('egypttax::receipts.synced', ['count' => $sync->handle()]));

        return self::SUCCESS;
    }
}
