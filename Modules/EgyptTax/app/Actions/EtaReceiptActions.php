<?php

namespace Modules\EgyptTax\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Models\EtaReceipt;

/**
 * What a user does with E-Receipts from the receipts log.
 */
class EtaReceiptActions
{
    public function __construct(
        private readonly IssueReceipt $issue,
        private readonly SubmitReceipts $submit,
        private readonly SyncReceipts $sync,
    ) {}

    /**
     * Builds an unbuilt receipt or sends a waiting one now, without waiting for its retry time.
     */
    public function retry(User $actor, EtaReceipt $receipt): EtaReceipt
    {
        Gate::forUser($actor)->authorize('egypttax.receipts.submit');

        if ($receipt->status === EtaReceiptStatus::Unbuilt) {
            $receipt = $this->issue->build($receipt);
        } elseif ($receipt->status === EtaReceiptStatus::Pending) {
            // Retry times are per device: the first waiting receipt holds the others back.
            EtaReceipt::where('device_id', $receipt->device_id)->where('status', EtaReceiptStatus::Pending)->update(['next_attempt_at' => null]);
        } else {
            throw ValidationException::withMessages(['receipt' => __('egypttax::receipts.not_retryable')]);
        }

        $this->submit->handle($receipt->device_id);

        return $receipt->refresh();
    }

    /**
     * Issues an invalid receipt again (after its data was fixed) as a new E-Receipt.
     */
    public function reissue(User $actor, EtaReceipt $receipt): EtaReceipt
    {
        Gate::forUser($actor)->authorize('egypttax.receipts.submit');

        if ($receipt->status !== EtaReceiptStatus::Invalid) {
            throw ValidationException::withMessages(['receipt' => __('egypttax::receipts.not_reissuable')]);
        }

        $replacement = $this->issue->reissue($receipt);
        $this->submit->handle($replacement->device_id);

        return $replacement->refresh();
    }

    /**
     * Sends everything waiting and reads the results of earlier submissions.
     *
     * @return array{accepted: int, updated: int}
     */
    public function sendAll(User $actor): array
    {
        Gate::forUser($actor)->authorize('egypttax.receipts.submit');

        DB::table('eta_receipts')->where('status', EtaReceiptStatus::Pending->value)->update(['next_attempt_at' => null]);

        return ['accepted' => $this->submit->handle(), 'updated' => $this->sync->handle()];
    }
}
