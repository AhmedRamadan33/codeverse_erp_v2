<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Audit\Auditable;
use Modules\Core\Documents\DocumentStatus;

/**
 * @property int $id
 * @property string|null $number
 * @property int $branch_id
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property \Carbon\CarbonImmutable $date
 * @property DocumentStatus $status
 */
class StockTransfer extends Model
{
    use Auditable;

    public const SEQUENCE = 'inventory.transfer';

    protected $fillable = [
        'number', 'branch_id', 'from_warehouse_id', 'to_warehouse_id', 'date', 'status', 'description',
        'created_by', 'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => DocumentStatus::class,
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('line_no');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
