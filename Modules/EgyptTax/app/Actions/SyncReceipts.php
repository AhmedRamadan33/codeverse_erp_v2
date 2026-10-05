<?php

namespace Modules\EgyptTax\Actions;

use Carbon\CarbonImmutable;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Eta\EtaClient;
use Modules\EgyptTax\Eta\EtaErrors;
use Modules\EgyptTax\Eta\EtaRequestFailed;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;

/**
 * Reads ETA's validation result (valid or invalid) of submitted E-Receipts.
 */
class SyncReceipts
{
    public function __construct(private readonly EtaClient $client) {}

    /**
     * @return int receipts whose status changed
     */
    public function handle(): int
    {
        $eta = EtaSetting::current();
        if (! $eta->isUsable()) {
            return 0;
        }

        $changed = 0;
        $submissions = EtaReceipt::where('status', EtaReceiptStatus::Submitted)
            ->whereNotNull('submission_uuid')
            ->distinct()
            ->get(['device_id', 'submission_uuid']);

        foreach ($submissions as $submission) {
            $device = EtaDevice::find($submission->device_id);

            try {
                $details = $this->client->submission($device, $eta, $submission->submission_uuid);
            } catch (EtaRequestFailed) {
                // Tried again on the next run.
                continue;
            }

            $byUuid = collect($details['receipts'] ?? [])->keyBy('uuid');
            $receipts = EtaReceipt::where('submission_uuid', $submission->submission_uuid)->where('status', EtaReceiptStatus::Submitted)->get();

            foreach ($receipts as $receipt) {
                $result = $byUuid->get($receipt->uuid);
                $status = match (strtolower((string) ($result['status'] ?? ''))) {
                    'valid' => EtaReceiptStatus::Valid,
                    'invalid' => EtaReceiptStatus::Invalid,
                    default => null,
                };

                if ($status !== null) {
                    $receipt->update([
                        'status' => $status,
                        'validated_at' => CarbonImmutable::now(),
                        'errors' => $status === EtaReceiptStatus::Invalid ? EtaErrors::messages($result['errors'] ?? $result['error'] ?? []) : null,
                        'long_id' => $result['longId'] ?? $receipt->long_id,
                    ]);
                    $changed++;
                }
            }
        }

        return $changed;
    }
}
