<?php

namespace Modules\EgyptTax\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaItemCode;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\EgyptTax\Models\EtaTaxCode;
use Modules\EgyptTax\Models\EtaUnitCode;

/**
 * ETA credentials, devices and code mappings. Secrets are write-only: a blank value keeps the stored one.
 */
class SettingsActions
{
    /**
     * @return array<string, mixed>
     */
    public static function settingsRules(): array
    {
        return [
            'environment' => ['required', Rule::in(EtaSetting::ENVIRONMENTS)],
            'rin' => ['nullable', 'required_if:is_active,true', 'string', 'max:32'],
            'company_trade_name' => ['nullable', 'required_if:is_active,true', 'string', 'max:255'],
            'activity_code' => ['nullable', 'required_if:is_active,true', 'string', 'max:8'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'exempt_tax_type' => ['required', 'string', 'max:8'],
            'exempt_sub_type' => ['required', 'string', 'max:16'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deviceRules(?EtaDevice $device = null): array
    {
        return [
            'register_id' => ['required', 'integer', Rule::exists('pos_registers', 'id'), Rule::unique('eta_devices', 'register_id')->ignore($device?->id)],
            'serial' => ['required', 'string', 'max:64', Rule::unique('eta_devices', 'serial')->ignore($device?->id)],
            'os_version' => ['required', 'string', 'max:64'],
            'model_framework' => ['nullable', 'string', 'max:64'],
            'pre_shared_key' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:16'],
            'address' => ['required', 'array'],
            'address.country' => ['required', 'string', 'size:2'],
            'address.governate' => ['required', 'string', 'max:100'],
            'address.regionCity' => ['required', 'string', 'max:100'],
            'address.street' => ['required', 'string', 'max:200'],
            'address.buildingNumber' => ['required', 'string', 'max:100'],
            'address.postalCode' => ['nullable', 'string', 'max:30'],
            'address.floor' => ['nullable', 'string', 'max:100'],
            'address.room' => ['nullable', 'string', 'max:100'],
            'address.landmark' => ['nullable', 'string', 'max:500'],
            'address.additionalInformation' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against settingsRules()
     */
    public function saveSettings(User $actor, array $data): EtaSetting
    {
        Gate::forUser($actor)->authorize('egypttax.settings.manage');

        return DB::transaction(function () use ($data) {
            $settings = EtaSetting::lockForUpdate()->orderBy('id')->first() ?? new EtaSetting;
            $settings->fill([
                'environment' => $data['environment'],
                'rin' => $data['rin'] ?? null,
                'company_trade_name' => $data['company_trade_name'] ?? null,
                'activity_code' => $data['activity_code'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'exempt_tax_type' => $data['exempt_tax_type'],
                'exempt_sub_type' => $data['exempt_sub_type'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
            if (filled($data['client_secret'] ?? null)) {
                $settings->client_secret = $data['client_secret'];
            }
            $settings->save();

            return $settings;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated against deviceRules()
     */
    public function saveDevice(User $actor, array $data, ?EtaDevice $device = null): EtaDevice
    {
        Gate::forUser($actor)->authorize('egypttax.settings.manage');

        return DB::transaction(function () use ($data, $device) {
            $device ??= new EtaDevice;
            $address = [];
            foreach (EtaDevice::ADDRESS_FIELDS as $field) {
                $address[$field] = $data['address'][$field] ?? '';
            }

            $device->fill([
                'register_id' => $data['register_id'],
                'serial' => $data['serial'],
                'os_version' => $data['os_version'],
                'model_framework' => $data['model_framework'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'branch_code' => $data['branch_code'],
                'address' => $address,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);
            if (filled($data['pre_shared_key'] ?? null)) {
                $device->pre_shared_key = $data['pre_shared_key'];
            }
            if (blank($data['client_id'] ?? null)) {
                // Back to the company's credentials.
                $device->client_secret = null;
            } elseif (filled($data['client_secret'] ?? null)) {
                $device->client_secret = $data['client_secret'];
            }
            $device->save();

            return $device;
        });
    }

    /**
     * A blank code removes the mapping.
     */
    public function saveItemCode(User $actor, int $productId, ?string $type, ?string $code): void
    {
        Gate::forUser($actor)->authorize('egypttax.settings.manage');

        validator(['product_id' => $productId, 'code_type' => $type, 'item_code' => $code], [
            'product_id' => ['required', Rule::exists('products', 'id')],
            'code_type' => ['required_with:item_code', 'nullable', Rule::in(EtaItemCode::TYPES)],
            'item_code' => ['nullable', 'string', 'max:100'],
        ])->validate();

        blank($code)
            ? EtaItemCode::where('product_id', $productId)->delete()
            : EtaItemCode::updateOrCreate(['product_id' => $productId], ['code_type' => $type, 'item_code' => trim($code)]);
    }

    public function saveUnitCode(User $actor, int $unitId, ?string $unitType): void
    {
        Gate::forUser($actor)->authorize('egypttax.settings.manage');

        validator(['unit_id' => $unitId, 'unit_type' => $unitType], [
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'unit_type' => ['nullable', 'string', 'max:16'],
        ])->validate();

        blank($unitType)
            ? EtaUnitCode::where('unit_id', $unitId)->delete()
            : EtaUnitCode::updateOrCreate(['unit_id' => $unitId], ['unit_type' => strtoupper(trim($unitType))]);
    }

    public function saveTaxCode(User $actor, int $taxId, ?string $taxType, ?string $subType): void
    {
        Gate::forUser($actor)->authorize('egypttax.settings.manage');

        validator(['tax_id' => $taxId, 'tax_type' => $taxType, 'sub_type' => $subType], [
            'tax_id' => ['required', Rule::exists('taxes', 'id')],
            'tax_type' => ['nullable', 'required_with:sub_type', 'string', 'max:8'],
            'sub_type' => ['nullable', 'required_with:tax_type', 'string', 'max:16'],
        ])->validate();

        blank($taxType)
            ? EtaTaxCode::where('tax_id', $taxId)->delete()
            : EtaTaxCode::updateOrCreate(['tax_id' => $taxId], ['tax_type' => strtoupper(trim($taxType)), 'sub_type' => strtoupper(trim($subType))]);
    }
}
