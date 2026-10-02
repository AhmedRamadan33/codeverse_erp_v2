<?php

namespace Modules\Core\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Modules\Core\Database\Factories\PartnerFactory;
use Modules\Core\Partners\PartnerType;

/**
 * A customer, a supplier or both. Balances are derived from journal lines in Accounting.
 *
 * @property int $id
 * @property PartnerType $type
 * @property string $name
 * @property bool $is_customer
 * @property bool $is_supplier
 * @property BigDecimal|null $credit_limit
 * @property int $payment_term_days
 * @property int|null $branch_id
 * @property bool $is_active
 */
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'type', 'name', 'is_customer', 'is_supplier', 'tax_number', 'national_id',
        'commercial_register', 'phone', 'email', 'address', 'credit_limit',
        'payment_term_days', 'branch_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PartnerType::class,
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'credit_limit' => AsDecimal::class.':4',
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('is_customer', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSuppliers(Builder $query): void
    {
        $query->where('is_supplier', true);
    }

    /**
     * Partners usable in the given branch: shared ones plus that branch's own.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAvailableIn(Builder $query, int $branchId): void
    {
        $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    protected static function newFactory(): PartnerFactory
    {
        return PartnerFactory::new();
    }
}
