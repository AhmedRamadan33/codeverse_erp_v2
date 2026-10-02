<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => ['ar' => 'فرع '.$this->faker->unique()->numberBetween(1, 9999), 'en' => 'Branch'],
            'code' => strtoupper($this->faker->unique()->bothify('BR###')),
            'is_active' => true,
        ];
    }
}
