<?php

namespace Modules\EgyptTax\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\Pos\Models\Receipt;

/**
 * The E-Receipt of a POS receipt.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $device_id
 * @property int|null $chain_no
 * @property string|null $uuid
 * @property string|null $previous_uuid
 * @property string|null $reference_uuid
 * @property string|null $reference_old_uuid
 * @property \Carbon\CarbonImmutable|null $issued_at
 * @property string|null $payload
 * @property EtaReceiptStatus $status
 * @property array<int, string>|null $errors
 * @property int $attempts
 * @property \Carbon\CarbonImmutable|null $next_attempt_at
 * @property string|null $submission_uuid
 * @property string|null $long_id
 * @property \Carbon\CarbonImmutable|null $submitted_at
 * @property \Carbon\CarbonImmutable|null $validated_at
 * @property int|null $replaced_by_id
 */
class EtaReceipt extends Model
{
    protected $table = 'eta_receipts';

    protected $fillable = [
        'receipt_id', 'device_id', 'chain_no', 'uuid', 'previous_uuid', 'reference_uuid', 'reference_old_uuid', 'issued_at',
        'payload', 'status', 'errors', 'attempts', 'next_attempt_at', 'submission_uuid', 'long_id', 'submitted_at',
        'validated_at', 'replaced_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EtaReceiptStatus::class,
            'errors' => 'array',
            'issued_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(EtaDevice::class, 'device_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    /**
     * The link printed as a QR code on the receipt; the buyer checks the receipt with it on the ETA portal.
     */
    public function shareUrl(EtaSetting $settings): ?string
    {
        if ($this->uuid === null || $this->payload === null) {
            return null;
        }

        $document = json_decode($this->payload, true);

        return sprintf(
            '%s/receipts/search/%s/share/%s#Total:%s,IssuerRIN:%s',
            $settings->endpoints()['portal'],
            $this->uuid,
            $document['header']['dateTimeIssued'],
            $document['totalAmount'],
            $document['seller']['rin'],
        );
    }
}
