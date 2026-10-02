<?php

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Core\Models\Partner
 */
class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'is_customer' => $this->is_customer,
            'is_supplier' => $this->is_supplier,
            'tax_number' => $this->tax_number,
            'national_id' => $this->national_id,
            'commercial_register' => $this->commercial_register,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            // Decimals travel as strings so clients never parse money as floats.
            'credit_limit' => $this->credit_limit === null ? null : (string) $this->credit_limit,
            'payment_term_days' => $this->payment_term_days,
            'branch_id' => $this->branch_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
