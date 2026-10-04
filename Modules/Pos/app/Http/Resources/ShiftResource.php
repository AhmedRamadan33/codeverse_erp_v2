<?php

namespace Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pos\Support\ShiftSummary;

/**
 * @mixin \Modules\Pos\Models\Shift
 */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = ShiftSummary::of($this->resource);
        $decimal = fn ($value) => $value === null ? null : (string) $value;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'register' => ['id' => $this->register_id, 'code' => $this->register->code, 'name' => $this->register->name],
            'cashier_id' => $this->user_id,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opening_float' => (string) $this->opening_float,
            'expected_cash' => $decimal($this->expected_cash ?? $summary->expectedCash),
            'counted_cash' => $decimal($this->counted_cash),
            'cash_difference' => $decimal($this->cash_difference),
            'sales' => ['count' => $summary->sales, 'total' => (string) $summary->salesTotal],
            'returns' => ['count' => $summary->returns, 'total' => (string) $summary->returnsTotal],
            'on_account' => (string) $summary->creditTotal,
            'payments' => collect($summary->byMethod)->map(fn (array $amounts, int $methodId) => [
                'payment_method_id' => $methodId,
                'received' => (string) $amounts['in'],
                'refunded' => (string) $amounts['out'],
            ])->values(),
        ];
    }
}
