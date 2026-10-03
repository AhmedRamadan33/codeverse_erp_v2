<?php

namespace Modules\Inventory\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use Modules\Accounting\Models\JournalEntry;
use Modules\Core\Casts\AsDecimal;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Products\Models\Product;

/**
 * Append-only: corrections are new moves pointing at the ones they reverse.
 *
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int|null $batch_id
 * @property BigDecimal $quantity
 * @property BigDecimal $unit_cost
 * @property BigDecimal $total_cost
 * @property StockMoveType $type
 * @property int|null $reversal_of_id
 * @property int|null $journal_entry_id
 * @property \Carbon\CarbonImmutable $date
 */
class StockMove extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_id', 'quantity', 'unit_cost', 'total_cost', 'type',
        'source_type', 'source_id', 'source_line_type', 'source_line_id', 'reversal_of_id',
        'journal_entry_id', 'date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => AsDecimal::class.':4',
            'unit_cost' => AsDecimal::class.':4',
            'total_cost' => AsDecimal::class.':4',
            'type' => StockMoveType::class,
            'date' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $move) {
            // Only stamping the valuation entry of a deferred move is allowed.
            if (array_diff(array_keys($move->getDirty()), ['journal_entry_id', 'updated_at']) !== [] || $move->getOriginal('journal_entry_id') !== null) {
                throw new LogicException('Stock moves are append-only; post a reversing move instead.');
            }
        });

        static::deleting(fn () => throw new LogicException('Stock moves are append-only; post a reversing move instead.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(StockSerial::class, 'stock_move_serials', 'stock_move_id', 'serial_id');
    }
}
