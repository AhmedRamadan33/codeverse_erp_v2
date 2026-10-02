<?php

namespace Modules\Accounting\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Modules\Accounting\Enums\TaxScope;
use Modules\Accounting\Enums\TaxType;
use Modules\Core\Audit\Auditable;
use Modules\Core\Casts\AsDecimal;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property BigDecimal $rate
 * @property TaxType $type
 * @property TaxScope $scope
 * @property bool $included_in_price
 * @property bool $is_active
 */
class Tax extends Model
{
    use Auditable, HasTranslations;

    /** @var string[] */
    public array $translatable = ['name'];

    protected $fillable = ['name', 'code', 'rate', 'type', 'scope', 'included_in_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => AsDecimal::class.':4',
            'type' => TaxType::class,
            'scope' => TaxScope::class,
            'included_in_price' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Tax on a net amount (exclusive of this tax), rounded to the given decimal places.
     * For a fixed tax, $quantity multiplies the fixed amount.
     */
    public function amountOn(BigDecimal $net, int $scale, BigDecimal|string|int $quantity = 1): BigDecimal
    {
        $amount = $this->type === TaxType::Percent
            ? $net->multipliedBy($this->rate)->dividedBy(100, $scale + 4, RoundingMode::HalfUp)
            : $this->rate->multipliedBy($quantity);

        return $amount->toScale($scale, RoundingMode::HalfUp);
    }
}
