<?php

namespace Modules\Core\Tests\Unit;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Casts\AsDecimal;
use PHPUnit\Framework\TestCase;

class AsDecimalTest extends TestCase
{
    private AsDecimal $cast;

    private Model $model;

    protected function setUp(): void
    {
        $this->cast = new AsDecimal(4);
        $this->model = new class extends Model {};
    }

    public function test_it_reads_database_strings_as_big_decimals_at_the_column_scale(): void
    {
        $value = $this->cast->get($this->model, 'amount', '10.5000', []);

        $this->assertInstanceOf(BigDecimal::class, $value);
        $this->assertSame('10.5000', (string) $value);
    }

    public function test_it_writes_exact_strings(): void
    {
        $this->assertSame('0.3000', $this->cast->set($this->model, 'amount', BigDecimal::of('0.1')->plus('0.2'), []));
        $this->assertSame('12.0000', $this->cast->set($this->model, 'amount', 12, []));
    }

    public function test_it_rejects_floats(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cast->set($this->model, 'amount', 0.1, []);
    }

    public function test_it_refuses_to_round_silently(): void
    {
        $this->expectException(RoundingNecessaryException::class);

        $this->cast->set($this->model, 'amount', '1.23456', []);
    }
}
