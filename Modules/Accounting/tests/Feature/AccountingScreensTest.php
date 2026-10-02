<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Accounting\Accounts\Actions\SaveAccount;
use Modules\Accounting\Livewire\Entries\Form;
use Modules\Accounting\Livewire\Entries\Show;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Tests\Concerns\PostsEntries;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class AccountingScreensTest extends TestCase
{
    use InstallsErp, PostsEntries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'accounts' => ['/accounting/accounts'],
            'entries' => ['/accounting/entries'],
            'entry form' => ['/accounting/entries/create'],
            'expenses' => ['/accounting/expenses'],
            'expense form' => ['/accounting/expenses/create'],
            'fiscal years' => ['/accounting/fiscal-years'],
            'mappings' => ['/accounting/mappings'],
            'taxes' => ['/accounting/taxes'],
            'payment methods' => ['/accounting/payment-methods'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_render_for_an_admin_and_are_refused_without_permission(string $url): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_a_manual_entry_is_drafted_in_the_form_then_posted_from_its_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Form::class)
            ->set('form.lines.0.account_id', $this->account('120101')->id)
            ->set('form.lines.0.debit', '2500.5')
            ->set('form.lines.1.account_id', $this->account('3101')->id)
            ->set('form.lines.1.credit', '2500.5')
            ->call('addLine')
            ->call('save')
            ->assertHasNoErrors();

        $entry = JournalEntry::sole();
        $this->assertCount(2, $entry->lines, 'The empty third line is dropped.');

        $this->get("/accounting/entries/{$entry->id}")->assertOk()->assertSee('2,500.50');

        Livewire::test(Show::class, ['id' => $entry->id])->call('post')->assertHasNoErrors();

        $this->assertTrue($entry->fresh()->isPosted());
    }

    public function test_an_unbalanced_draft_shows_the_posting_error(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Form::class)
            ->set('form.lines.0.account_id', $this->account('120101')->id)
            ->set('form.lines.0.debit', '100')
            ->set('form.lines.1.account_id', $this->account('3101')->id)
            ->set('form.lines.1.credit', '90')
            ->call('save');

        Livewire::test(Show::class, ['id' => JournalEntry::sole()->id])
            ->call('post')
            ->assertHasErrors('entry');
    }

    public function test_account_rules_protect_the_chart(): void
    {
        $data = fn (array $overrides) => array_merge([
            'code' => '120102', 'name_ar' => 'خزينة فرعية', 'parent_id' => $this->account('1201')->id,
            'type' => 'asset', 'subtype' => 'cash', 'is_group' => false, 'is_active' => true,
        ], $overrides);

        $box = app(SaveAccount::class)->handle($this->admin, $data([]));
        $this->assertSame('1201', $box->parent->code);

        foreach ([
            fn () => app(SaveAccount::class)->handle($this->admin, $data(['code' => '120103', 'type' => 'expense', 'subtype' => null])),
            fn () => app(SaveAccount::class)->handle($this->admin, $data(['code' => '120104', 'subtype' => 'revenue'])),
            fn () => app(SaveAccount::class)->handle($this->admin, array_merge($data([]), ['is_active' => false, 'code' => '120101', 'name_ar' => 'الخزينة الرئيسية']), Account::firstWhere('code', '120101')),
            fn () => app(SaveAccount::class)->delete($this->admin, Account::firstWhere('code', '1203')),
        ] as $i => $rejected) {
            try {
                $rejected();
                $this->fail("Case {$i} should have been rejected.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        app(SaveAccount::class)->delete($this->admin, $box);
        $this->assertModelMissing($box);
    }
}
