<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Partner;
use Modules\Core\Partners\PartnerType;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'type' => PartnerType::Company,
            'name' => $this->faker->company(),
            'is_customer' => true,
            'is_supplier' => false,
            'phone' => $this->faker->numerify('010########'),
            'is_active' => true,
        ];
    }

    public function supplier(): static
    {
        return $this->state(['is_customer' => false, 'is_supplier' => true]);
    }
}
