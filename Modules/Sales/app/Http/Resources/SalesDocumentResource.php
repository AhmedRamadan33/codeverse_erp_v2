<?php

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;

/**
 * A sales invoice or return. Decimals travel as strings so clients never parse money as floats.
 *
 * @mixin \Modules\Sales\Models\SalesDocument
 */
class SalesDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decimal = fn ($value) => $value === null ? null : (string) $value;
        $isInvoice = $this->resource instanceof SalesInvoice;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'date' => $this->date->toDateString(),
            'partner' => ['id' => $this->partner_id, 'name' => $this->partner?->name],
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (string) $this->exchange_rate,
            'subtotal' => (string) $this->subtotal,
            'discount_total' => (string) $this->discount_total,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'description' => $this->description,
            'journal_entry_id' => $this->journal_entry_id,
            $this->mergeWhen($isInvoice, fn () => [
                'due_date' => $this->due_date?->toDateString(),
                'price_list_id' => $this->price_list_id,
                'discount_type' => $this->discount_type,
                'discount_value' => $decimal($this->discount_value),
                'payment_method_id' => $this->payment_method_id,
                'paid_amount' => $decimal($this->paid_amount),
            ]),
            $this->mergeWhen($this->resource instanceof SalesReturn, fn () => [
                'sales_invoice_id' => $this->sales_invoice_id,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'sales_invoice_line_id' => $isInvoice ? null : $line->sales_invoice_line_id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'unit_id' => $line->unit_id,
                'quantity' => (string) $line->quantity,
                'unit_price' => $isInvoice ? (string) $line->unit_price : null,
                'discount_type' => $isInvoice ? $line->discount_type : null,
                'discount_value' => $isInvoice ? $decimal($line->discount_value) : null,
                'net' => (string) $line->net,
                'tax_id' => $line->tax_id,
                'tax_amount' => (string) $line->tax_amount,
                'line_total' => (string) $line->line_total,
                'batch_number' => $line->batch_number,
                'serials' => $line->serials,
                'returnable_quantity' => $isInvoice && $this->status->value === 'posted' ? (string) $line->returnableQuantity() : null,
            ])),
            'cancel_reason' => $this->cancel_reason,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
