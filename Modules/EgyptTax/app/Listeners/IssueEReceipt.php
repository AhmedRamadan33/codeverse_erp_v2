<?php

namespace Modules\EgyptTax\Listeners;

use Modules\EgyptTax\Actions\IssueReceipt;
use Modules\EgyptTax\Actions\SubmitReceipts;
use Modules\Pos\Events\PosReceiptCompleted;
use Throwable;

/**
 * Builds the E-Receipt of a completed POS receipt and sends it once the response is out, so
 * the sale never waits for ETA. What is not sent here is sent by `egypttax:submit`.
 */
class IssueEReceipt
{
    public function __construct(private readonly IssueReceipt $issue) {}

    public function handle(PosReceiptCompleted $event): void
    {
        try {
            $etaReceipt = $this->issue->forCompleted($event->receipt);
        } catch (Throwable $e) {
            // The sale is committed; the scheduled run cannot pick this one up, so make it visible.
            report($e);

            return;
        }

        if ($etaReceipt === null) {
            return;
        }

        app()->terminating(function () use ($etaReceipt) {
            try {
                app(SubmitReceipts::class)->handle($etaReceipt->device_id);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
