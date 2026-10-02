<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\CashVoucher;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Reconciliation\Reconciler;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Accounting\Vouchers\Actions\AllocateVoucher;
use Modules\Accounting\Vouchers\Actions\CancelVoucher;
use Modules\Accounting\Vouchers\Actions\PostVoucher;
use Modules\Accounting\Vouchers\Actions\SaveVoucher;
use Modules\Accounting\Vouchers\VoucherKind;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class VouchersTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    private Partner $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        $this->customer = Partner::factory()->create();
    }

    private function voucher(VoucherKind $kind, string $amount, array $overrides = []): CashVoucher
    {
        return app(SaveVoucher::class)->handle($this->admin, $kind, array_merge([
            'date' => now()->toDateString(),
            'branch_id' => $this->branch->id,
            'partner_id' => $this->customer->id,
            'payment_method_id' => PaymentMethod::where('type', 'cash')->value('id'),
            'currency_id' => Currency::where('code', 'EGP')->value('id'),
            'amount' => $amount,
        ], $overrides));
    }

    /**
     * An invoice-like open item: Dr receivable for the customer.
     */
    private function openItem(string $amount): JournalLine
    {
        return $this->postEntry([
            Line::debit($this->account('1203')->id, $amount, partnerId: $this->customer->id),
            Line::credit($this->account('4101')->id, $amount),
        ])->lines[0];
    }

    private function balance(): string
    {
        return (string) app(Ledger::class)->partnerBalance($this->customer, AccountSubtype::Receivable);
    }

    public function test_a_receipt_posts_cash_against_the_customer_and_settles_an_invoice_partially(): void
    {
        $invoice = $this->openItem('1000');
        $voucher = $this->voucher(VoucherKind::Receipt, '600');

        app(PostVoucher::class)->handle($this->admin, $voucher, [$invoice->id => '600']);

        $voucher->refresh();
        $this->assertSame(DocumentStatus::Posted, $voucher->status);
        $this->assertStringStartsWith('RV-', $voucher->number);
        $this->assertSame('600.0000', (string) app(Ledger::class)->accountBalance($this->account('120101')));
        $this->assertSame('400.0000', $this->balance());
        $this->assertSame('400.0000', (string) app(Reconciler::class)->residual($invoice));
        $this->assertSame('0.0000', (string) app(Reconciler::class)->residual($voucher->partnerLine()));
        $this->assertLedgerBalanced();
    }

    public function test_over_allocation_rolls_the_whole_posting_back(): void
    {
        $invoice = $this->openItem('1000');
        $voucher = $this->voucher(VoucherKind::Receipt, '300');

        try {
            app(PostVoucher::class)->handle($this->admin, $voucher, [$invoice->id => '500']);
            $this->fail('Over-allocation was accepted.');
        } catch (ValidationException) {
            $this->assertSame(DocumentStatus::Draft, $voucher->fresh()->status);
            $this->assertSame('1000.0000', $this->balance());
        }
    }

    public function test_an_unallocated_receipt_stays_open_and_can_be_allocated_later(): void
    {
        $voucher = $this->voucher(VoucherKind::Receipt, '250');
        app(PostVoucher::class)->handle($this->admin, $voucher);
        $this->assertSame('-250.0000', $this->balance());

        $invoice = $this->openItem('1000');
        app(AllocateVoucher::class)->handle($this->admin, $voucher->fresh(), [$invoice->id => '250']);

        $open = app(Reconciler::class)->openLines($this->customer->id, [$this->account('1203')->id], debitSide: true);
        $this->assertSame('750.0000', (string) $open->sole()['residual']);
    }

    public function test_a_foreign_currency_receipt_posts_base_amounts_with_the_foreign_amount(): void
    {
        $usd = Currency::firstWhere('code', 'USD');
        $usd->update(['is_active' => true]);

        $voucher = $this->voucher(VoucherKind::Receipt, '100', ['currency_id' => $usd->id, 'exchange_rate' => '48.55']);
        app(PostVoucher::class)->handle($this->admin, $voucher);

        $lines = $voucher->fresh()->journalEntry->lines;
        $this->assertSame('4855.0000', (string) $lines[0]->debit);
        $this->assertSame('100.0000', (string) $lines[0]->amount_currency);
        $this->assertSame('-100.0000', (string) $lines[1]->amount_currency);
    }

    public function test_a_payment_debits_the_supplier_and_credits_cash(): void
    {
        $voucher = $this->voucher(VoucherKind::Payment, '500');
        app(PostVoucher::class)->handle($this->admin, $voucher);

        $this->assertStringStartsWith('PV-', $voucher->fresh()->number);
        $this->assertSame('-500.0000', (string) app(Ledger::class)->accountBalance($this->account('120101')));
        $this->assertSame('500.0000', (string) app(Ledger::class)->partnerBalance($this->customer, AccountSubtype::Payable));
    }

    public function test_cancelling_releases_allocations_and_reverses_the_entry(): void
    {
        $invoice = $this->openItem('1000');
        $voucher = $this->voucher(VoucherKind::Receipt, '1000');
        app(PostVoucher::class)->handle($this->admin, $voucher, [$invoice->id => '1000']);

        app(CancelVoucher::class)->handle($this->admin, $voucher->fresh(), 'Bounced cheque');

        $voucher->refresh();
        $this->assertSame(DocumentStatus::Cancelled, $voucher->status);
        $this->assertNotNull($voucher->journalEntry->reversed_by_id);
        $this->assertSame('1000.0000', $this->balance());
        $this->assertSame('1000.0000', (string) app(Reconciler::class)->residual($invoice));
        $this->assertLedgerBalanced();
    }

    public function test_amounts_respect_the_currency_decimals(): void
    {
        $this->expectException(ValidationException::class);

        $this->voucher(VoucherKind::Receipt, '10.005');
    }
}
