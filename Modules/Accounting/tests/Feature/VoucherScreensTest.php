<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Accounting\Livewire\Vouchers\Form;
use Modules\Accounting\Livewire\Vouchers\Show;
use Modules\Accounting\Models\ReceiptVoucher;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class VoucherScreensTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->actingAs($this->admin);
    }

    public function test_voucher_pages_render_for_both_kinds(): void
    {
        foreach (['receipts', 'payments'] as $path) {
            $this->get("/accounting/{$path}")->assertOk();
            $this->get("/accounting/{$path}/create")->assertOk();
        }
    }

    public function test_a_receipt_is_drafted_then_posted_with_allocations_filled_in_order(): void
    {
        $customer = Partner::factory()->create();
        $first = $this->postEntry([
            Line::debit($this->account('1203')->id, '300', partnerId: $customer->id),
            Line::credit($this->account('4101')->id, '300'),
        ])->lines[0];
        $second = $this->postEntry([
            Line::debit($this->account('1203')->id, '500', partnerId: $customer->id),
            Line::credit($this->account('4101')->id, '500'),
        ])->lines[0];

        Livewire::test(Form::class, ['kind' => 'receipt'])
            ->set('form.partner_id', $customer->id)
            ->set('form.amount', '600')
            ->call('save')
            ->assertHasNoErrors();

        $voucher = ReceiptVoucher::sole();

        Livewire::test(Show::class, ['kind' => 'receipt', 'id' => $voucher->id])
            ->call('fillInOrder')
            ->assertSet('allocations', [$first->id => '300.0000', $second->id => '300.0000'])
            ->call('post')
            ->assertHasNoErrors();

        $this->assertSame(DocumentStatus::Posted, $voucher->fresh()->status);
        $this->assertTrue(app(Reconciler::class)->residual($first)->isZero());
        $this->assertSame('200.0000', (string) app(Reconciler::class)->residual($second));

        $this->get("/accounting/receipts/{$voucher->id}")->assertOk()->assertSee($voucher->fresh()->number);
    }
}
