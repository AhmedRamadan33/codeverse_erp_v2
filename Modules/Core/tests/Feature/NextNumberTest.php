<?php

namespace Modules\Core\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Sequence;
use Modules\Core\Sequences\NextNumber;
use Modules\Core\Sequences\SequenceReset;
use Modules\Core\Sequences\Sequences;
use Tests\TestCase;

class NextNumberTest extends TestCase
{
    use RefreshDatabase;

    private NextNumber $next;

    protected function setUp(): void
    {
        parent::setUp();

        $this->next = app(NextNumber::class);
        app(Sequences::class)->define('test.invoice', 'INV-{yyyy}-', 4);
    }

    private function number(string $date, ?int $branchId = null, string $key = 'test.invoice'): string
    {
        return DB::transaction(fn () => $this->next->handle($key, $branchId, CarbonImmutable::parse($date)));
    }

    public function test_numbers_increase_and_are_formatted(): void
    {
        $this->assertSame('INV-2026-0001', $this->number('2026-10-01'));
        $this->assertSame('INV-2026-0002', $this->number('2026-10-02'));
    }

    public function test_a_yearly_sequence_restarts_each_year_and_keeps_counting_the_old_year(): void
    {
        $this->assertSame('INV-2026-0001', $this->number('2026-12-31'));
        $this->assertSame('INV-2027-0001', $this->number('2027-01-01'));
        // A document dated in the previous year, posted after the reset, continues that year's numbers.
        $this->assertSame('INV-2026-0002', $this->number('2026-12-30'));
        $this->assertSame('INV-2027-0002', $this->number('2027-01-02'));
    }

    public function test_a_never_resetting_sequence_ignores_the_year(): void
    {
        app(Sequences::class)->define('test.voucher', 'V', 3, SequenceReset::Never);

        $this->assertSame('V001', $this->number('2026-01-01', key: 'test.voucher'));
        $this->assertSame('V002', $this->number('2027-01-01', key: 'test.voucher'));
    }

    public function test_a_branch_sequence_is_used_before_the_shared_one(): void
    {
        $branch = Branch::factory()->create(['code' => 'ALX']);
        $other = Branch::factory()->create(['code' => 'CAI']);
        Sequence::create(['key' => 'test.invoice', 'branch_id' => $branch->id, 'prefix' => '{branch}-', 'padding' => 3, 'reset' => SequenceReset::Never]);

        $this->assertSame('ALX-001', $this->number('2026-10-01', $branch->id));
        $this->assertSame('INV-2026-0001', $this->number('2026-10-01', $other->id));
    }

    public function test_it_refuses_to_run_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        $this->next->handle('test.invoice', null, CarbonImmutable::now());
    }

    public function test_an_undefined_sequence_is_an_error(): void
    {
        $this->expectException(LogicException::class);

        $this->number('2026-10-01', key: 'test.missing');
    }
}
