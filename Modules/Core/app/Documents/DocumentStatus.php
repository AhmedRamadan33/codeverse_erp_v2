<?php

namespace Modules\Core\Documents;

/**
 * Lifecycle shared by every document (core-design.md §5): only drafts are edited;
 * posted documents are corrected by cancelling (reversal) or by a return document.
 */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('core::documents.status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-warning',
            self::Posted => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
