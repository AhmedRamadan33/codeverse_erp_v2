<?php

namespace Modules\EgyptTax\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Eta\MissingEtaData;
use Modules\EgyptTax\Eta\ReceiptDocument;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\Pos\Models\Receipt;

/**
 * Records the E-Receipt of a POS receipt and builds it into its device's uuid chain.
 *
 * Building takes a lock on the device, so receipts get their chain position and previous
 * uuid one at a time. A receipt whose data is incomplete stays "unbuilt" (outside the chain)
 * with the reasons, and is built once the data is fixed.
 */
class IssueReceipt
{
    public function __construct(private readonly ReceiptDocument $document) {}

    /**
     * For a completed POS receipt; null when E-Receipts are off or its register is not an ETA device.
     */
    public function forCompleted(Receipt $receipt): ?EtaReceipt
    {
        $eta = EtaSetting::current();
        $device = EtaDevice::where('register_id', $receipt->register_id)->where('is_active', true)->first();

        if (! $eta->isUsable() || $device === null) {
            return null;
        }

        return DB::transaction(function () use ($receipt, $device, $eta) {
            $device = EtaDevice::lockForUpdate()->findOrFail($device->id);

            if ($existing = EtaReceipt::where('receipt_id', $receipt->id)->latest('id')->first()) {
                return $existing;
            }

            $etaReceipt = EtaReceipt::create([
                'receipt_id' => $receipt->id,
                'device_id' => $device->id,
                'status' => EtaReceiptStatus::Unbuilt,
                'issued_at' => $receipt->created_at,
            ]);

            return $this->buildLocked($etaReceipt, $device, $eta);
        });
    }

    /**
     * Builds an unbuilt E-Receipt, e.g. after its missing codes were entered.
     */
    public function build(EtaReceipt $etaReceipt): EtaReceipt
    {
        return DB::transaction(function () use ($etaReceipt) {
            $device = EtaDevice::lockForUpdate()->findOrFail($etaReceipt->device_id);
            $etaReceipt = EtaReceipt::lockForUpdate()->findOrFail($etaReceipt->id);

            return $etaReceipt->status === EtaReceiptStatus::Unbuilt
                ? $this->buildLocked($etaReceipt, $device, EtaSetting::current())
                : $etaReceipt;
        });
    }

    /**
     * Issues an invalid E-Receipt again as a new one that refers to it (referenceOldUUID).
     */
    public function reissue(EtaReceipt $invalid): EtaReceipt
    {
        return DB::transaction(function () use ($invalid) {
            $device = EtaDevice::lockForUpdate()->findOrFail($invalid->device_id);
            $invalid = EtaReceipt::lockForUpdate()->findOrFail($invalid->id);

            $replacement = EtaReceipt::create([
                'receipt_id' => $invalid->receipt_id,
                'device_id' => $device->id,
                'reference_old_uuid' => $invalid->uuid,
                'status' => EtaReceiptStatus::Unbuilt,
                'issued_at' => CarbonImmutable::now(),
            ]);
            $invalid->update(['status' => EtaReceiptStatus::Replaced, 'replaced_by_id' => $replacement->id]);

            return $this->buildLocked($replacement, $device, EtaSetting::current());
        });
    }

    /**
     * Caller holds the device lock.
     */
    private function buildLocked(EtaReceipt $etaReceipt, EtaDevice $device, EtaSetting $eta): EtaReceipt
    {
        $receipt = $etaReceipt->receipt;
        $previous = EtaReceipt::where('device_id', $device->id)->whereNotNull('chain_no')->orderByDesc('chain_no')->first();
        $reference = $receipt->original_receipt_id === null ? null : EtaReceipt::where('receipt_id', $receipt->original_receipt_id)
            ->whereNotNull('uuid')
            ->whereNull('replaced_by_id')
            ->latest('id')
            ->value('uuid');

        try {
            $document = $this->document->build(
                $receipt, $device, $eta, $previous->uuid ?? '', $reference, $etaReceipt->reference_old_uuid, $etaReceipt->issued_at,
            );
        } catch (MissingEtaData $e) {
            $etaReceipt->update(['errors' => $e->errors]);

            return $etaReceipt;
        }

        $etaReceipt->update([
            'chain_no' => ($previous->chain_no ?? 0) + 1,
            'uuid' => $document['header']['uuid'],
            'previous_uuid' => $previous?->uuid,
            'reference_uuid' => $reference,
            'payload' => json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'status' => EtaReceiptStatus::Pending,
            'errors' => null,
        ]);

        return $etaReceipt;
    }
}
