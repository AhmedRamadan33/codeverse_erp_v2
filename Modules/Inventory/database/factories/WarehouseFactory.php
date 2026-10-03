<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;
use Modules\Inventory\Models\Warehouse;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => ['ar' => 'مخزن '.$this->faker->unique()->numberBetween(1, 9999)],
            'code' => strtoupper($this->faker->unique()->bothify('WH###')),
            'branch_id' => fn () => Branch::query()->value('id'),
            'is_active' => true,
        ];
    }
}
