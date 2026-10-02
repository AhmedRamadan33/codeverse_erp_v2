<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entries\Actions\PostDraftEntry;
use Modules\Accounting\Entries\Actions\ReverseManualEntry;
use Modules\Accounting\Entries\Actions\SaveDraftEntry;
use Modules\Accounting\Enums\AccountSubtype;
use Modules\Accounting\Enums\EntryStatus;
use Modules\Accounting\Ledger\Ledger;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Posting\JournalLineData as Line;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use Modules\Core\Models\Partner;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class ManualEntriesAndLedgerTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    private function draftData(array $lines): array
    {
        return [
            'date' => CarbonImmutable::now()->toDateString(),
            'branch_id' => $this->branch->id,
            'journal_type' => 'general',
            'description' => 'Owner capital',
            'lines' => $lines,
        ];
    }

    public function test_a_draft_can_be_unbalanced_but_posts_only_when_balanced(): void
    {
        $cash = $this->account('120101')->id;
        $capital = $this->account('3101')->id;

        $draft = app(SaveDraftEntry::class)->handle($this->admin, $this->draftData([
            ['account_id' => $cash, 'debit' => '50000'],
            ['account_id' => $capital, 'credit' => '40000'],
        ]));

        $this->assertSame(EntryStatus::Draft, $draft->status);
        $this->assertNull($draft->number);

        $this->expectException(ValidationException::class);
        app(PostDraftEntry::class)->handle($this->admin, $draft);
    }

    public function test_posting_a_balanced_draft_numbers_it_and_updates_balances(): void
    {
        $draft = app(SaveDraftEntry::class)->handle($this->admin, $this->draftData([
            ['account_id' => $this->account('120101')->id, 'debit' => '50000'],
            ['account_id' => $this->account('3101')->id, 'credit' => '50000'],
        ]));

        $entry = app(PostDraftEntry::class)->handle($this->admin, $draft);

        $this->assertTrue($entry->isPosted());
        $this->assertNotNull($entry->number);
        $this->assertSame('50000.0000', (string) app(Ledger::class)->accountBalance($this->account('120101')));
        // Group account totals its children.
        $this->assertSame('50000.0000', (string) app(Ledger::class)->accountBalance($this->account('1')));
        $this->assertSame('-50000.0000', (string) app(Ledger::class)->accountBalance($this->account('3')));

        $this->expectException(ValidationException::class);
        app(SaveDraftEntry::class)->handle($this->admin, $this->draftData([]), $entry);
    }

    public function test_a_clerk_without_the_post_permission_can_only_save_drafts(): void
    {
        $clerk = User::factory()->create();
        $clerk->givePermissionTo('accounting.entries.create');
        $clerk->branches()->attach($this->branch);

        $draft = app(SaveDraftEntry::class)->handle($clerk, $this->draftData([
            ['account_id' => $this->account('120101')->id, 'debit' => '10'],
            ['account_id' => $this->account('3101')->id, 'credit' => '10'],
        ]));

        $this->expectException(AuthorizationException::class);
        app(PostDraftEntry::class)->handle($clerk, $draft);
    }

    public function test_entries_from_documents_are_not_reversed_by_hand(): void
    {
        $entry = $this->postEntry([
            Line::debit($this->account('120101')->id, '10'),
            Line::credit($this->account('4101')->id, '10'),
        ]);
        JournalEntry::whereKey($entry->id)->toBase()->update(['source_type' => 'sales_invoice', 'source_id' => 1]);

        $this->expectException(ValidationException::class);
        app(ReverseManualEntry::class)->handle($this->admin, $entry->fresh(), CarbonImmutable::now(), 'x');
    }

    public function test_partner_balance_and_trial_balance_come_from_posted_lines(): void
    {
        $customer = Partner::factory()->create();
        $receivable = $this->account('1203')->id;
        $start = CarbonImmutable::now()->startOfYear();

        $this->postEntry([
            Line::debit($receivable, '1140', partnerId: $customer->id),
            Line::credit($this->account('4101')->id, '1000'),
            Line::credit($this->account('2103')->id, '140'),
        ], $start->toDateString());
        $this->postEntry([
            Line::debit($this->account('120101')->id, '1000'),
            Line::credit($receivable, '1000', partnerId: $customer->id),
        ], $start->addMonth()->toDateString());

        $this->assertSame('140.0000', (string) app(Ledger::class)->partnerBalance($customer, AccountSubtype::Receivable));

        $rows = app(Ledger::class)->trialBalance($start->addMonth(), $start->addMonth()->endOfMonth())->keyBy(fn ($row) => $row->account->code);

        $this->assertSame('1140.0000', (string) $rows['1203']->opening);
        $this->assertSame('1000.0000', (string) $rows['1203']->credit);
        $this->assertSame('140.0000', (string) $rows['1203']->closing);
        $this->assertSame('-1000.0000', (string) $rows['4101']->closing);
        $this->assertTrue($rows->reduce(fn ($sum, $row) => $sum->plus($row->closing), BigDecimal::zero())->isZero());
    }

    public function test_tax_amounts_are_rounded_half_up_to_the_currency_scale(): void
    {
        $vat = Tax::firstWhere('code', 'VAT14');

        $this->assertSame('14.00', (string) $vat->amountOn(BigDecimal::of('100'), 2));
        $this->assertSame('1.73', (string) $vat->amountOn(BigDecimal::of('12.35'), 2)); // 1.729
        $this->assertSame('0.07', (string) $vat->amountOn(BigDecimal::of('0.5'), 2));   // 0.07
    }
}
