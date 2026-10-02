<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'code' => (string) $this->faker->unique()->numberBetween(900000, 999999),
            'name' => ['ar' => 'حساب تجريبي', 'en' => 'Test account'],
            'type' => AccountType::Expense,
            'subtype' => AccountSubtype::OperatingExpense,
            'is_group' => false,
            'is_active' => true,
        ];
    }
}
