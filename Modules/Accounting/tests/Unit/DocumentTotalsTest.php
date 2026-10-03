<?php

namespace Modules\Accounting\Tests\Unit;

use Modules\Accounting\Enums\TaxType;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Pricing\Discount;
use Modules\Accounting\Pricing\DocumentTotals;
use Modules\Accounting\Pricing\PricedLine;
use PHPUnit\Framework\TestCase;

class DocumentTotalsTest extends TestCase
{
    private function vat(): Tax
    {
        return new Tax(['rate' => '14', 'type' => TaxType::Percent]);
    }

    public function test_line_discount_document_discount_and_tax(): void
    {
        $result = (new DocumentTotals)->calculate([
            new PricedLine('3', '100', Discount::percent('10'), $this->vat()),   // gross 300, -30 → 270
            new PricedLine('1', '130', null, $this->vat()),                      // gross 130 → 130
        ], 2, Discount::amount('40'));

        // 40 split 270:130 → 27.00 and 13.00
        $this->assertSame('27.00', (string) $result->lines[0]->documentDiscount);
        $this->assertSame('13.00', (string) $result->lines[1]->documentDiscount);
        $this->assertSame('243.00', (string) $result->lines[0]->net);
        $this->assertSame('34.02', (string) $result->lines[0]->tax);
        $this->assertSame('430.00', (string) $result->subtotal());
        $this->assertSame('70.00', (string) $result->discountTotal());
        $this->assertSame('360.00', (string) $result->netTotal());
        $this->assertSame('410.40', (string) $result->total());
    }

    public function test_the_document_discount_remainder_goes_to_the_largest_line(): void
    {
        $result = (new DocumentTotals)->calculate([
            new PricedLine('1', '10'),
            new PricedLine('1', '10'),
            new PricedLine('1', '10.01'),
        ], 2, Discount::amount('10'));

        $shares = array_map(fn ($l) => (string) $l->documentDiscount, $result->lines);
        $this->assertSame(['3.33', '3.33', '3.34'], $shares);
        $this->assertSame('10.00', (string) $result->discountTotal());
    }

    public function test_a_discount_never_exceeds_its_base_and_three_decimal_currencies_work(): void
    {
        $result = (new DocumentTotals)->calculate([new PricedLine('2', '1.2345', Discount::amount('5'))], 3);

        $this->assertSame('2.469', (string) $result->lines[0]->gross);
        $this->assertSame('2.469', (string) $result->lines[0]->lineDiscount);
        $this->assertSame('0.000', (string) $result->total());
    }

    public function test_fixed_taxes_multiply_by_quantity(): void
    {
        $stamp = new Tax(['rate' => '0.5', 'type' => TaxType::Fixed]);

        $result = (new DocumentTotals)->calculate([new PricedLine('4', '10', null, $stamp)], 2);

        $this->assertSame('2.00', (string) $result->lines[0]->tax);
    }
}
