<?php

namespace Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pos\Models\ReceiptLine;
use Modules\Pos\Models\ReceiptPayment;

/**
 * Decimals travel as strings so clients never parse money as floats.
 *
 * @mixin \Modules\Pos\Models\Receipt
 */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'shift_id' => $this->shift_id,
            'register_id' => $this->register_id,
            'partner' => ['id' => $this->partner_id, 'name' => $this->partner?->name],
            'original_receipt_id' => $this->original_receipt_id,
            'date' => $this->date->toDateString(),
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'paid_total' => (string) $this->paid_total,
            'tendered' => $this->tendered === null ? null : (string) $this->tendered,
            'change' => (string) $this->change,
            'is_credit' => $this->is_credit,
            'due_date' => $this->due_date?->toDateString(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn (ReceiptLine $line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'unit_id' => $line->unit_id,
                'unit_name' => $line->unit?->name,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'discount' => (string) $line->line_discount->plus($line->document_discount),
                'net' => (string) $line->net,
                'tax_amount' => (string) $line->tax_amount,
                'line_total' => (string) $line->line_total,
                'serials' => $line->serials ?? [],
                'returnable_quantity' => $this->isReturn() ? null : (string) $line->returnableQuantity(),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (ReceiptPayment $payment) => [
                'payment_method_id' => $payment->payment_method_id,
                'payment_method' => $payment->method?->name,
                'amount' => (string) $payment->amount,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
