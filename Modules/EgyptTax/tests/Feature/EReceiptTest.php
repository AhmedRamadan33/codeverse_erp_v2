<?php

namespace Modules\EgyptTax\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Accounting\Models\Tax;
use Modules\Core\Models\Partner;
use Modules\EgyptTax\Actions\EtaReceiptActions;
use Modules\EgyptTax\Actions\SettingsActions;
use Modules\EgyptTax\Actions\SubmitReceipts;
use Modules\EgyptTax\Actions\SyncReceipts;
use Modules\EgyptTax\Enums\EtaReceiptStatus;
use Modules\EgyptTax\Eta\Serializer;
use Modules\EgyptTax\Livewire\Codes;
use Modules\EgyptTax\Livewire\Receipts\Index as ReceiptsLog;
use Modules\EgyptTax\Livewire\Settings;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaItemCode;
use Modules\EgyptTax\Models\EtaReceipt;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\EgyptTax\Models\EtaUnitCode;
use Modules\Inventory\Enums\StockMoveType;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Stock\StockLineData;
use Modules\Inventory\Tests\Concerns\MovesStock;
use Modules\Pos\Actions\ReceiptActions;
use Modules\Pos\Actions\RegisterActions;
use Modules\Pos\Actions\ShiftActions;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\Register;
use Modules\Products\Models\Product;
use Tests\Concerns\InstallsErp;
use Tests\TestCase;

class EReceiptTest extends TestCase
{
    use InstallsErp, MovesStock, RefreshDatabase;

    private const TOKEN_URL = 'https://id.preprod.eta.gov.eg/connect/token';

    private const SUBMIT_URL = 'https://api.preprod.invoicing.eta.gov.eg/api/v1/receiptsubmissions';

    private Product $product;

    private Register $register;

    private EtaDevice $device;

    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installErp();
        Http::preventStrayRequests();

        $warehouse = Warehouse::where('branch_id', $this->branch->id)->firstOrFail();
        $this->cash = PaymentMethod::where('type', 'cash')->value('id');
        $this->product = Product::factory()->create(['sku' => 'AB1', 'sale_price' => '10', 'sale_tax_id' => Tax::firstWhere('code', 'VAT14')->id]);
        $this->receive([new StockLineData($this->product->id, $warehouse->id, '100', '6')], null, StockMoveType::Opening, 'opening_balance_equity');

        $this->register = app(RegisterActions::class)->save($this->admin, [
            'code' => 'R1', 'name_ar' => 'كاشير 1', 'warehouse_id' => $warehouse->id, 'cash_payment_method_id' => $this->cash,
        ]);
        app(ShiftActions::class)->open($this->admin, $this->register, '0');

        $actions = app(SettingsActions::class);
        $actions->saveSettings($this->admin, [
            'environment' => 'preprod', 'rin' => '623913607', 'company_trade_name' => 'Acme', 'activity_code' => '4630',
            'client_id' => 'company-client', 'client_secret' => 'company-secret', 'exempt_tax_type' => 'T1', 'exempt_sub_type' => 'V003',
            'is_active' => true,
        ]);
        $this->device = $actions->saveDevice($this->admin, [
            'register_id' => $this->register->id, 'serial' => 'MJ02GWXH', 'os_version' => 'windows', 'model_framework' => '1',
            'pre_shared_key' => 'psk-value', 'branch_code' => '0', 'is_active' => true,
            'address' => ['country' => 'EG', 'governate' => 'Cairo', 'regionCity' => 'Ain Shams', 'street' => '16 street', 'buildingNumber' => '9'],
        ]);
        $actions->saveItemCode($this->admin, $this->product->id, 'EGS', 'EG-623913607-AB1');
        $actions->saveUnitCode($this->admin, $this->product->base_unit_id, 'ea');
    }

    private function sell(string $quantity = '2', ?Partner $customer = null, array $line = [], ?string $paid = null): Receipt
    {
        return app(ReceiptActions::class)->sell($this->admin, [
            'partner_id' => $customer?->id,
            'lines' => [array_merge(['product_id' => $this->product->id, 'unit_id' => $this->product->base_unit_id, 'quantity' => $quantity], $line)],
            'payments' => [['payment_method_id' => $this->cash, 'amount' => $paid ?? '1000']],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function document(EtaReceipt $etaReceipt): array
    {
        return json_decode($etaReceipt->payload, true);
    }

    private function fakeEta(array $rejectUuids = []): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
            self::SUBMIT_URL => function (Request $request) use ($rejectUuids) {
                $uuids = collect(json_decode($request->body(), true)['receipts'])->pluck('header.uuid');

                return Http::response([
                    'submissionId' => 'SUB-1',
                    'acceptedDocuments' => $uuids->diff($rejectUuids)->map(fn ($u) => ['uuid' => $u, 'longId' => 'L-'.$u])->values()->all(),
                    'rejectedDocuments' => $uuids->intersect($rejectUuids)->map(fn ($u) => ['uuid' => $u, 'error' => ['message' => 'Validation error', 'details' => [['message' => 'Bad item code']]]])->values()->all(),
                ], 202);
            },
        ]);
    }

    public function test_the_serializer_follows_the_eta_canonical_form(): void
    {
        $serializer = new Serializer;
        $doc = ['header' => ['uuid' => 'x', 'n' => 1], 'items' => [['a' => 'b', 'q' => 2.5], ['a' => 'c', 'q' => 1]], 'tags' => []];

        $this->assertSame('"HEADER""UUID""x""N""1""ITEMS""ITEMS""A""b""Q""2.5""ITEMS""A""c""Q""1""TAGS"', $serializer->serialize($doc));
        // The uuid is the hash of the document with an empty uuid, so it does not depend on itself.
        $this->assertSame($serializer->uuid($doc), $serializer->uuid(array_replace_recursive($doc, ['header' => ['uuid' => 'other']])));
        $this->assertSame(64, strlen($serializer->uuid($doc)));
    }

    public function test_a_sale_on_an_eta_device_builds_a_pending_e_receipt_with_exact_amounts(): void
    {
        Carbon::setTestNow('2026-10-05 10:20:30');
        $receipt = $this->sell();
        Carbon::setTestNow();

        $eta = EtaReceipt::where('receipt_id', $receipt->id)->sole();
        $this->assertSame(EtaReceiptStatus::Pending, $eta->status);
        $this->assertSame(1, $eta->chain_no);
        $this->assertNull($eta->previous_uuid);

        $doc = $this->document($eta);
        $this->assertSame('2026-10-05T10:20:30Z', $doc['header']['dateTimeIssued']);
        $this->assertSame($receipt->number, $doc['header']['receiptNumber']);
        $this->assertSame('', $doc['header']['previousUUID']);
        $this->assertSame(['receiptType' => 'S', 'typeVersion' => '1.2'], $doc['documentType']);
        $this->assertSame('623913607', $doc['seller']['rin']);
        $this->assertSame('MJ02GWXH', $doc['seller']['deviceSerialNumber']);
        $this->assertSame('Ain Shams', $doc['seller']['branchAddress']['regionCity']);
        $this->assertSame('', $doc['seller']['branchAddress']['floor']);
        $this->assertSame(['type' => 'P', 'id' => '', 'name' => '', 'mobileNumber' => '', 'paymentNumber' => ''], $doc['buyer']);
        $this->assertSame([
            'internalCode' => 'AB1',
            'description' => $this->product->getTranslation('name', 'ar', true),
            'itemType' => 'EGS',
            'itemCode' => 'EG-623913607-AB1',
            'unitType' => 'EA',
            'quantity' => 2,
            'unitPrice' => 10,
            'netSale' => 20,
            'totalSale' => 20,
            'total' => 22.8,
            'valueDifference' => 0,
            'taxableItems' => [['taxType' => 'T1', 'amount' => 2.8, 'subType' => 'V009', 'rate' => 14]],
        ], $doc['itemData'][0]);
        $this->assertSame(20, $doc['totalSales']);
        $this->assertSame(0, $doc['totalCommercialDiscount']);
        $this->assertSame(20, $doc['netAmount']);
        $this->assertSame(22.8, $doc['totalAmount']);
        $this->assertSame([['taxType' => 'T1', 'amount' => 2.8]], $doc['taxTotals']);
        $this->assertSame('C', $doc['paymentMethod']);

        // The stored JSON is what is hashed and sent: its uuid checks out.
        $this->assertSame($eta->uuid, $doc['header']['uuid']);
        $this->assertSame($eta->uuid, (new Serializer)->uuid($doc));
        $this->assertStringContainsString('"totalAmount":22.8,', $eta->payload);
    }

    public function test_receipts_of_a_device_form_a_chain(): void
    {
        $first = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $second = EtaReceipt::where('receipt_id', $this->sell('1')->id)->sole();

        $this->assertSame(2, $second->chain_no);
        $this->assertSame($first->uuid, $second->previous_uuid);
        $this->assertSame($first->uuid, $this->document($second)['header']['previousUUID']);
        $this->assertNotSame($first->uuid, $second->uuid);
    }

    public function test_rounding_goes_into_the_commercial_discount_so_net_sale_matches_the_books(): void
    {
        // 3 × 10.005 = 30.015 → 30.02 at 2 decimals, less 1 → net 29.02, VAT 4.06, total 33.08.
        $receipt = $this->sell('3', null, ['unit_price' => '10.005', 'discount_type' => 'amount', 'discount_value' => '1']);
        $item = $this->document(EtaReceipt::where('receipt_id', $receipt->id)->sole())['itemData'][0];

        $this->assertSame('29.0200', (string) $receipt->lines()->sole()->net);
        $this->assertSame(30.015, $item['totalSale']);
        $this->assertSame([['amount' => 0.995, 'description' => 'Discount']], $item['commercialDiscountData']);
        $this->assertSame(29.02, $item['netSale']);
        $this->assertSame(4.06, $item['taxableItems'][0]['amount']);
        $this->assertSame(33.08, $item['total']);
    }

    public function test_nothing_is_issued_when_e_receipts_are_off_or_the_register_is_not_a_device(): void
    {
        EtaSetting::query()->update(['is_active' => false]);
        $this->sell();

        EtaSetting::query()->update(['is_active' => true]);
        $this->device->update(['is_active' => false]);
        $this->sell();

        $this->assertSame(0, EtaReceipt::count());
    }

    public function test_missing_codes_leave_the_receipt_unbuilt_until_they_are_entered(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]), self::SUBMIT_URL => Http::response([], 500)]);
        EtaItemCode::query()->delete();
        EtaUnitCode::query()->delete();

        $eta = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $this->assertSame(EtaReceiptStatus::Unbuilt, $eta->status);
        $this->assertNull($eta->uuid);
        $this->assertNull($eta->chain_no);
        $this->assertCount(2, $eta->errors);
        $this->assertStringContainsString('AB1', $eta->errors[0]);

        // A later receipt that is complete takes the first place in the chain.
        app(SettingsActions::class)->saveUnitCode($this->admin, $this->product->base_unit_id, 'EA');
        app(SettingsActions::class)->saveItemCode($this->admin, $this->product->id, 'EGS', 'EG-623913607-AB1');
        app(SubmitReceipts::class)->handle();

        $eta->refresh();
        // Built into the chain; the send failed (500), so it waits for the next try.
        $this->assertSame(EtaReceiptStatus::Pending, $eta->status);
        $this->assertSame(1, $eta->chain_no);
        $this->assertNotNull($eta->uuid);
        $this->assertStringContainsString('500', $eta->errors[0]);
    }

    public function test_submission_sends_the_exact_payloads_with_the_device_headers_and_records_the_result(): void
    {
        $this->fakeEta();
        $first = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $second = EtaReceipt::where('receipt_id', $this->sell('1')->id)->sole();

        $this->assertSame(2, app(SubmitReceipts::class)->handle());

        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL
            && $r->header('posserial') === ['MJ02GWXH']
            && $r->header('pososversion') === ['windows']
            && $r->header('presharedkey') === ['psk-value']
            && $r['grant_type'] === 'client_credentials'
            && $r['client_id'] === 'company-client'
            && $r['client_secret'] === 'company-secret');
        Http::assertSent(fn (Request $r) => $r->url() === self::SUBMIT_URL
            && $r->header('Authorization') === ['Bearer token-1']
            && $r->body() === '{"receipts":['.$first->payload.','.$second->payload.']}');

        foreach ([$first, $second] as $eta) {
            $eta->refresh();
            $this->assertSame(EtaReceiptStatus::Submitted, $eta->status);
            $this->assertSame('SUB-1', $eta->submission_uuid);
            $this->assertSame('L-'.$eta->uuid, $eta->long_id);
            $this->assertSame(1, $eta->attempts);
        }
    }

    public function test_a_rejected_receipt_is_invalid_with_eta_errors_and_can_be_reissued(): void
    {
        $eta = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $this->fakeEta([$eta->uuid]);

        app(SubmitReceipts::class)->handle();
        $eta->refresh();
        $this->assertSame(EtaReceiptStatus::Invalid, $eta->status);
        $this->assertSame(['Validation error', 'Bad item code'], $eta->errors);

        $replacement = app(EtaReceiptActions::class)->reissue($this->admin, $eta);

        $this->assertSame(EtaReceiptStatus::Replaced, $eta->refresh()->status);
        $this->assertSame($replacement->id, $eta->replaced_by_id);
        $this->assertSame($eta->uuid, $replacement->reference_old_uuid);
        $this->assertSame($eta->uuid, $this->document($replacement)['header']['referenceOldUUID']);
        $this->assertSame($eta->uuid, $replacement->previous_uuid);
        $this->assertSame(EtaReceiptStatus::Submitted, $replacement->status);
    }

    public function test_a_failed_submission_waits_and_retries_later_without_sending_again_meanwhile(): void
    {
        $answer = Http::response(['error' => ['message' => 'Server busy']], 503);
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 't', 'expires_in' => 3600]), self::SUBMIT_URL => function () use (&$answer) {
            return $answer;
        }]);
        $eta = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();

        app(SubmitReceipts::class)->handle();
        $eta->refresh();
        $this->assertSame(EtaReceiptStatus::Pending, $eta->status);
        $this->assertSame(1, $eta->attempts);
        $this->assertTrue($eta->next_attempt_at->isFuture());
        $this->assertStringContainsString('503', $eta->errors[0]);
        $this->assertStringContainsString('Server busy', $eta->errors[0]);

        app(SubmitReceipts::class)->handle();
        Http::assertSentCount(2);   // token + one submission; the second run waited

        // A duplicate refused by ETA waits as long as it asks.
        $answer = Http::response(['error' => 'duplicate'], 422, ['Retry-After' => '600']);
        $this->travel(5)->minutes();
        app(SubmitReceipts::class)->handle();
        $this->assertEqualsWithDelta(600, now()->diffInSeconds($eta->refresh()->next_attempt_at), 5);
    }

    public function test_an_expired_token_is_renewed_once(): void
    {
        $tokens = ['old', 'new'];
        Http::fake([
            self::TOKEN_URL => function () use (&$tokens) {
                return Http::response(['access_token' => array_shift($tokens), 'expires_in' => 3600]);
            },
            self::SUBMIT_URL => fn (Request $r) => $r->header('Authorization') === ['Bearer old']
                ? Http::response([], 401)
                : Http::response(['submissionId' => 'S', 'acceptedDocuments' => [], 'rejectedDocuments' => []], 202),
        ]);
        $eta = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();

        app(SubmitReceipts::class)->handle();

        $this->assertSame(EtaReceiptStatus::Submitted, $eta->refresh()->status);
    }

    public function test_sync_reads_the_validation_result(): void
    {
        $this->fakeEta();
        $valid = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $invalid = EtaReceipt::where('receipt_id', $this->sell('1')->id)->sole();
        app(SubmitReceipts::class)->handle();

        Http::fake(['https://api.preprod.invoicing.eta.gov.eg/api/v1/receiptsubmissions/SUB-1/details*' => Http::response(['receipts' => [
            ['uuid' => $valid->uuid, 'status' => 'Valid'],
            ['uuid' => $invalid->uuid, 'status' => 'Invalid', 'errors' => [['message' => 'Wrong tax']]],
        ]])]);

        $this->assertSame(2, app(SyncReceipts::class)->handle());
        $this->assertSame(EtaReceiptStatus::Valid, $valid->refresh()->status);
        $this->assertNotNull($valid->validated_at);
        $this->assertSame(EtaReceiptStatus::Invalid, $invalid->refresh()->status);
        $this->assertSame(['Wrong tax'], $invalid->errors);
    }

    public function test_a_return_refers_to_the_sale_e_receipt(): void
    {
        $sale = $this->sell();
        $saleEta = EtaReceipt::where('receipt_id', $sale->id)->sole();

        $return = app(ReceiptActions::class)->return($this->admin, $sale, [
            'lines' => [['original_line_id' => $sale->lines()->value('id'), 'quantity' => '1']],
        ]);
        $doc = $this->document(EtaReceipt::where('receipt_id', $return->id)->sole());

        $this->assertSame('R', $doc['documentType']['receiptType']);
        $this->assertSame($saleEta->uuid, $doc['header']['referenceUUID']);
        $this->assertSame($saleEta->uuid, $doc['header']['previousUUID']);
        $this->assertSame(11.4, $doc['totalAmount']);
    }

    public function test_buyer_rules(): void
    {
        $this->fakeEta();
        $company = Partner::factory()->create(['type' => 'company', 'name' => 'Beta Co', 'is_customer' => true, 'tax_number' => null]);
        $eta = EtaReceipt::where('receipt_id', $this->sell('1', $company)->id)->sole();
        $this->assertSame(EtaReceiptStatus::Unbuilt, $eta->status);
        $this->assertStringContainsString('Beta Co', $eta->errors[0]);

        $company->update(['tax_number' => '100200300']);
        app(EtaReceiptActions::class)->retry($this->admin, $eta->fresh());
        $this->assertSame(['type' => 'B', 'id' => '100200300', 'name' => 'Beta Co'], array_slice($this->document($eta->refresh())['buyer'], 0, 3));

        // A person needs a national id from 150,000 EGP; the walk-in customer cannot buy that much anonymously.
        config(['egypttax.buyer_id_threshold' => '20']);
        $person = Partner::factory()->create(['type' => 'person', 'name' => 'Sara', 'is_customer' => true, 'national_id' => null]);
        $this->assertSame(EtaReceiptStatus::Unbuilt, EtaReceipt::where('receipt_id', $this->sell('2', $person)->id)->sole()->status);
        $this->assertSame(EtaReceiptStatus::Unbuilt, EtaReceipt::where('receipt_id', $this->sell('2')->id)->sole()->status);
        $this->assertSame(EtaReceiptStatus::Pending, EtaReceipt::where('receipt_id', $this->sell('1')->id)->sole()->status);
    }

    public function test_secrets_are_encrypted_and_kept_out_of_the_audit_log(): void
    {
        $raw = DB::table('eta_settings')->value('client_secret');
        $this->assertNotSame('company-secret', $raw);
        $this->assertSame('company-secret', EtaSetting::current()->client_secret);
        $this->assertNotSame('psk-value', DB::table('eta_devices')->value('pre_shared_key'));

        $audit = DB::table('audit_logs')->whereIn('auditable_type', [(new EtaSetting)->getMorphClass(), (new EtaDevice)->getMorphClass()])->get();
        $this->assertNotEmpty($audit);
        foreach ($audit as $row) {
            $this->assertStringNotContainsString('company-secret', (string) $row->new_values);
            $this->assertStringNotContainsString('client_secret', (string) $row->new_values);
            $this->assertStringNotContainsString('pre_shared_key', (string) $row->new_values);
        }

        // A blank secret keeps the stored one.
        Livewire::actingAs($this->admin)->test(Settings::class)
            ->assertSet('form.client_secret', '')
            ->set('form.company_trade_name', 'Acme Egypt')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('company-secret', EtaSetting::current()->client_secret);
        $this->assertSame('Acme Egypt', EtaSetting::current()->company_trade_name);
    }

    public function test_screens_and_permissions(): void
    {
        $eta = EtaReceipt::where('receipt_id', $this->sell()->id)->sole();
        $this->actingAs($this->admin);

        foreach (['egypttax.receipts.index', 'egypttax.codes', 'egypttax.devices', 'egypttax.settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('egypttax.receipts.index'))->assertSee($eta->uuid);

        $other = Product::factory()->create(['sku' => 'ZZ9']);
        Livewire::test(Codes::class)
            ->assertSee('ZZ9')
            ->set("items.{$other->id}.item_code", 'EG-623913607-ZZ9')
            ->call('saveItem', $other->id)
            ->assertHasNoErrors();
        $this->assertSame('EG-623913607-ZZ9', EtaItemCode::firstWhere('product_id', $other->id)->item_code);

        $this->fakeEta();
        Livewire::test(ReceiptsLog::class)->call('retry', $eta->id);
        $this->assertSame(EtaReceiptStatus::Submitted, $eta->refresh()->status);

        // The printed receipt shows the E-Receipt uuid and QR code.
        $this->get(route('pos.receipts.print', $eta->receipt_id))->assertOk()->assertSee($eta->uuid)->assertSee('<svg', false);

        $nobody = User::factory()->create();
        foreach (['egypttax.receipts.index', 'egypttax.codes', 'egypttax.devices', 'egypttax.settings'] as $route) {
            $this->actingAs($nobody)->get(route($route))->assertForbidden();
        }
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('egypttax.receipts.view');
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(EtaReceiptActions::class)->sendAll($viewer);
    }
}
