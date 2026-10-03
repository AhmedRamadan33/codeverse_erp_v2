<?php

namespace Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Audit\Auditable;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;

/**
 * @property int $id
 * @property string|null $number
 * @property int $branch_id
 * @property int $warehouse_id
 * @property \Carbon\CarbonImmutable $date
 * @property DocumentStatus $status
 * @property string $kind adjustment|opening
 */
class StockAdjustment extends Model
{
    use Auditable;

    public const SEQUENCE = 'inventory.adjustment';

    protected $fillable = [
        'number', 'branch_id', 'warehouse_id', 'date', 'status', 'kind', 'description',
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
        return $this->hasMany(StockAdjustmentLine::class)->orderBy('line_no');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpening(): bool
    {
        return $this->kind === 'opening';
    }
}
