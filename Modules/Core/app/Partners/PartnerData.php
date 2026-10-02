<?php

namespace Modules\Core\Partners;

use Illuminate\Validation\Rule;

/**
 * Input for creating or updating a partner; shared by the web UI and the API.
 */
final readonly class PartnerData
{
    public function __construct(
        public PartnerType $type,
        public string $name,
        public bool $isCustomer,
        public bool $isSupplier,
        public ?string $taxNumber = null,
        public ?string $nationalId = null,
        public ?string $commercialRegister = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $creditLimit = null,
        public int $paymentTermDays = 0,
        public ?int $branchId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PartnerType::class)],
            'name' => ['required', 'string', 'max:255'],
            // At least one role is required; checked by the actions so the rule lives in one place.
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'national_id' => ['nullable', 'string', 'max:32'],
            'commercial_register' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999999999999'],
            'payment_term_days' => ['integer', 'min:0', 'max:3650'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  validated input in snake_case
     */
    public static function fromArray(array $input): self
    {
        $creditLimit = $input['credit_limit'] ?? null;

        return new self(
            type: PartnerType::from($input['type']),
            name: trim($input['name']),
            isCustomer: (bool) ($input['is_customer'] ?? false),
            isSupplier: (bool) ($input['is_supplier'] ?? false),
            taxNumber: $input['tax_number'] ?? null,
            nationalId: $input['national_id'] ?? null,
            commercialRegister: $input['commercial_register'] ?? null,
            phone: $input['phone'] ?? null,
            email: $input['email'] ?? null,
            address: $input['address'] ?? null,
            // Keep decimals as strings end to end; never through float.
            creditLimit: $creditLimit === null || $creditLimit === '' ? null : (string) $creditLimit,
            paymentTermDays: (int) ($input['payment_term_days'] ?? 0),
            branchId: isset($input['branch_id']) && $input['branch_id'] !== '' ? (int) $input['branch_id'] : null,
            isActive: (bool) ($input['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'is_customer' => $this->isCustomer,
            'is_supplier' => $this->isSupplier,
            'tax_number' => $this->taxNumber,
            'national_id' => $this->nationalId,
            'commercial_register' => $this->commercialRegister,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'credit_limit' => $this->creditLimit,
            'payment_term_days' => $this->paymentTermDays,
            'branch_id' => $this->branchId,
            'is_active' => $this->isActive,
        ];
    }
}
