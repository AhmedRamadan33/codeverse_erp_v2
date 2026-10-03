<?php

namespace Modules\Inventory\Livewire\Concerns;

use Modules\Products\Models\Product;

/**
 * Product lines in stock document forms: units follow the chosen product;
 * serials are typed comma separated and sent as lists.
 */
trait EditsStockLines
{
    /**
     * @return array<string, mixed>
     */
    protected function blankLine(): array
    {
        return ['product_id' => null, 'unit_id' => null, 'quantity' => '', 'unit_cost' => '', 'batch_number' => '', 'expiry_date' => null, 'serials' => '', 'description' => ''];
    }

    public function addLine(): void
    {
        $this->form['lines'][] = $this->blankLine();
    }

    public function removeLine(int $i): void
    {
        unset($this->form['lines'][$i]);
        $this->form['lines'] = array_values($this->form['lines']);
    }

    /**
     * Livewire hook for any change inside form: picking a product selects its default unit.
     */
    public function updatedForm(mixed $value, string $key): void
    {
        if (preg_match('/^lines\.(\d+)\.product_id$/', $key, $m) && $value) {
            $product = Product::with('units')->find($value);
            $this->form['lines'][(int) $m[1]]['unit_id'] = $product?->units->firstWhere('is_default_purchase', true)?->unit_id ?? $product?->base_unit_id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $data = $this->form;
        $data['lines'] = array_values(array_filter($data['lines'], fn ($l) => ! empty($l['product_id']) || ($l['quantity'] ?? '') !== ''));
        $data['lines'] = array_map(function (array $line) {
            $line['serials'] = array_values(array_filter(array_map('trim', explode(',', (string) ($line['serials'] ?? '')))));
            $line['unit_cost'] = ($line['unit_cost'] ?? '') === '' ? null : $line['unit_cost'];
            $line['batch_number'] = ($line['batch_number'] ?? '') === '' ? null : $line['batch_number'];
            $line['expiry_date'] = ($line['expiry_date'] ?? '') === '' ? null : $line['expiry_date'];

            return $line;
        }, $data['lines']);

        return $data;
    }

    /**
     * Stocked products with their units for the line pickers.
     */
    protected function productOptions()
    {
        return Product::with('units.unit')->where('type', 'stockable')->where('is_active', true)->orderBy('sku')->get();
    }
}
