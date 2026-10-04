<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Core\Audit\Auditable;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\Warehouse;
use Modules\Pos\Enums\ShiftStatus;
use Modules\Sales\Models\PriceList;
use Spatie\Translatable\HasTranslations;

/**
 * A till: sells from one warehouse and keeps cash in one drawer (a cash payment method).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $branch_id
 * @property int $warehouse_id
 * @property int $cash_payment_method_id
 * @property int|null $price_list_id
 * @property bool $is_active
 */
class Register extends Model
{
    use Auditable, HasTranslations;

    protected $table = 'pos_registers';

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = ['code', 'name', 'branch_id', 'warehouse_id', 'cash_payment_method_id', 'price_list_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'cash_payment_method_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', ShiftStatus::Open)->first();
    }
}
