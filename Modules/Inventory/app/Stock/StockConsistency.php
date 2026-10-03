<?php

namespace Modules\Inventory\Stock;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Compares the caches (stock_balances, product_costs) with Σ stock_moves and can rebuild
 * them (core-design.md §9.1).
 */
class StockConsistency
{
    /**
     * @return array<int, string> human-readable differences; empty when consistent
     */
    public function differences(): array
    {
        $issues = [];

        $balances = DB::select(<<<'SQL'
            select coalesce(m.product_id, b.product_id) as product_id, coalesce(m.warehouse_id, b.warehouse_id) as warehouse_id,
                   coalesce(m.batch_id, b.batch_id) as batch_id, coalesce(m.qty, 0) as moves_qty, coalesce(b.quantity, 0) as cached_qty
            from (select product_id, warehouse_id, batch_id, sum(quantity) qty from stock_moves group by product_id, warehouse_id, batch_id) m
            left join stock_balances b on b.product_id = m.product_id and b.warehouse_id = m.warehouse_id and b.batch_scope = coalesce(m.batch_id, 0)
            union
            select b.product_id, b.warehouse_id, b.batch_id, 0, b.quantity from stock_balances b
            where b.quantity <> 0 and not exists (select 1 from stock_moves m where m.product_id = b.product_id and m.warehouse_id = b.warehouse_id and coalesce(m.batch_id, 0) = b.batch_scope)
            SQL);

        foreach ($balances as $row) {
            if (! BigDecimal::of($row->moves_qty)->isEqualTo($row->cached_qty)) {
                $issues[] = "balance product={$row->product_id} warehouse={$row->warehouse_id} batch={$row->batch_id}: moves {$row->moves_qty}, cached {$row->cached_qty}";
            }
        }

        // Transfers move stock between warehouses without changing company-wide totals.
        $costs = DB::select(<<<'SQL'
            select m.product_id, sum(m.quantity) qty, sum(m.total_cost) value, c.quantity_on_hand, c.total_value
            from stock_moves m left join product_costs c on c.product_id = m.product_id
            where m.type not in ('transfer_in', 'transfer_out')
            group by m.product_id, c.quantity_on_hand, c.total_value
            SQL);

        foreach ($costs as $row) {
            if (! BigDecimal::of($row->qty)->isEqualTo($row->quantity_on_hand ?? 0) || ! BigDecimal::of($row->value)->isEqualTo($row->total_value ?? 0)) {
                $issues[] = "cost product={$row->product_id}: moves {$row->qty} / {$row->value}, cached {$row->quantity_on_hand} / {$row->total_value}";
            }
        }

        return $issues;
    }
}
