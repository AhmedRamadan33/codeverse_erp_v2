<?php

namespace Modules\Core\Tests\Unit;

use Modules\Core\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_without_float_errors(): void
    {
        $this->assertSame('1,234,567.80', Money::format('1234567.8'));
        $this->assertSame('-1,000.00', Money::format('-999.995'));
        $this->assertSame('0.30', Money::format('0.3000'));
        $this->assertSame('99,999,999,999,999.99', Money::format('99999999999999.9900'));
        $this->assertSame('1.235', Money::format('1.2345', 3));
        $this->assertSame('', Money::format(null));
    }
}
