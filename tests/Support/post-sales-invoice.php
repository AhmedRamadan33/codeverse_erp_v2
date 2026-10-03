<?php

/*
 * Posts one sales invoice in its own PHP process, for the concurrency test.
 * Usage: php tests/Support/post-sales-invoice.php <invoice id> <user id>
 * Prints "posted" or "refused: <message>".
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Modules\Sales\Actions\InvoiceActions;
use Modules\Sales\Models\SalesInvoice;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$invoiceId, $userId] = [(int) $argv[1], (int) $argv[2]];

try {
    $app->make(InvoiceActions::class)->post(User::findOrFail($userId), SalesInvoice::findOrFail($invoiceId));
    echo 'posted';
} catch (ValidationException $e) {
    echo 'refused: '.collect($e->errors())->flatten()->first();
}
