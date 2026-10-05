<?php

namespace Modules\EgyptTax\Enums;

enum EtaReceiptStatus: string
{
    // Data is missing (item code, buyer id, …); not in the chain yet.
    case Unbuilt = 'unbuilt';
    // Built and waiting to be sent (or to be retried).
    case Pending = 'pending';
    // Accepted by ETA, waiting for validation.
    case Submitted = 'submitted';
    case Valid = 'valid';
    case Invalid = 'invalid';
    // An invalid receipt that was reissued as a new E-Receipt.
    case Replaced = 'replaced';

    public function label(): string
    {
        return __("egypttax::receipts.status.{$this->value}");
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unbuilt => 'text-bg-warning',
            self::Pending => 'text-bg-secondary',
            self::Submitted => 'text-bg-info',
            self::Valid => 'text-bg-success',
            self::Invalid => 'text-bg-danger',
            self::Replaced => 'text-bg-light',
        };
    }
}
