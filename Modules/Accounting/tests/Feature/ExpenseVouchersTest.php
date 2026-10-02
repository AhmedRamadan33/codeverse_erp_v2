<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Expenses\Actions\PostExpenseVoucher;
use Modules\Accounting\Expenses\Actions\SaveExpenseVoucher;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class ExpenseVouchersTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    private function data(array $lines): array
    {
        return [
            'date' => now()->toDateString(),
            'branch_id' => $this->branch->id,
            'payment_method_id' => PaymentMethod::where('type', 'cash')->value('id'),
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'description' => 'Office costs',
            'lines' => $lines,
        ];
    }

    public function test_an_expense_voucher_computes_tax_and_posts_expense_tax_and_cash(): void
    {
        $vat = Tax::firstWhere('code', 'VAT14');

        $voucher = app(SaveExpenseVoucher::class)->handle($this->admin, $this->data([
            ['account_id' => $this->account('5203')->id, 'amount' => '1000', 'tax_id' => $vat->id],
            ['account_id' => $this->account('5205')->id, 'amount' => '85.50'],
        ]));

        $this->assertSame('1085.5000', (string) $voucher->subtotal);
        $this->assertSame('140.0000', (string) $voucher->tax_total);
        $this->assertSame('1225.5000', (string) $voucher->total);

        app(PostExpenseVoucher::class)->handle($this->admin, $voucher);

        $ledger = app(Ledger::class);
        $this->assertSame(DocumentStatus::Posted, $voucher->fresh()->status);
        $this->assertStringStartsWith('EV-', $voucher->fresh()->number);
        $this->assertSame('1000.0000', (string) $ledger->accountBalance($this->account('5203')));
        $this->assertSame('140.0000', (string) $ledger->accountBalance($this->account('1206')));
        $this->assertSame('-1225.5000', (string) $ledger->accountBalance($this->account('120101')));
        $this->assertLedgerBalanced();
    }

    public function test_revenue_and_partner_accounts_are_refused(): void
    {
        foreach (['4101', '1203'] as $code) {
            try {
                app(SaveExpenseVoucher::class)->handle($this->admin, $this->data([
                    ['account_id' => $this->account($code)->id, 'amount' => '10'],
                ]));
                $this->fail("Account {$code} was accepted.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cancelling_reverses_the_entry(): void
    {
        $voucher = app(SaveExpenseVoucher::class)->handle($this->admin, $this->data([
            ['account_id' => $this->account('5202')->id, 'amount' => '5000'],
        ]));
        app(PostExpenseVoucher::class)->handle($this->admin, $voucher);

        app(PostExpenseVoucher::class)->cancel($this->admin, $voucher->fresh(), 'Duplicate');

        $this->assertSame(DocumentStatus::Cancelled, $voucher->fresh()->status);
        $this->assertTrue(app(Ledger::class)->accountBalance($this->account('5202'))->isZero());
    }
}
