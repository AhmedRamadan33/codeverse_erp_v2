<?php

namespace Modules\EgyptTax\Eta;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Accounting\Enums\PaymentMethodType;
use Modules\Accounting\Enums\TaxType;
use Modules\Core\Partners\PartnerType;
use Modules\Core\Settings\Settings;
use Modules\EgyptTax\Models\EtaDevice;
use Modules\EgyptTax\Models\EtaItemCode;
use Modules\EgyptTax\Models\EtaSetting;
use Modules\EgyptTax\Models\EtaTaxCode;
use Modules\EgyptTax\Models\EtaUnitCode;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Models\ReceiptLine;

/**
 * Builds the ETA E-Receipt (v1.2) of a POS receipt, sale or return, with its uuid.
 *
 * Amounts come from the posted receipt, so the E-Receipt matches the books: each line's
 * discounts become its commercial discount, so netSale is the line's net exactly.
 */
class ReceiptDocument
{
    public const TYPE_VERSION = '1.2';

    public function __construct(
        private readonly Serializer $serializer,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws MissingEtaData
     */
    public function build(
        Receipt $receipt,
        EtaDevice $device,
        EtaSetting $eta,
        string $previousUuid,
        ?string $referenceUuid,
        ?string $referenceOldUuid,
        CarbonImmutable $issuedAt,
    ): array {
        $receipt->loadMissing(['lines.product', 'lines.tax', 'payments.method', 'partner']);
        $errors = [];

        $items = [];
        $taxTotals = [];
        $totalSales = BigDecimal::zero();
        $totalDiscount = BigDecimal::zero();

        foreach ($receipt->lines as $line) {
            $item = $this->item($line, $eta, $errors);
            if ($item === null) {
                continue;
            }

            $items[] = $item['data'];
            $totalSales = $totalSales->plus($item['total_sale']);
            $totalDiscount = $totalDiscount->plus($item['discount']);
            foreach ($item['data']['taxableItems'] as $tax) {
                $taxTotals[$tax['taxType']] = ($taxTotals[$tax['taxType']] ?? BigDecimal::zero())->plus($item['tax_amount']);
            }
        }

        $buyer = $this->buyer($receipt, $errors);

        if ($receipt->isReturn() && $referenceUuid === null) {
            $errors[] = __('egypttax::receipts.errors.original_not_issued');
        }

        if ($errors !== []) {
            throw new MissingEtaData(array_values(array_unique($errors)));
        }

        $header = [
            'dateTimeIssued' => $issuedAt->utc()->format('Y-m-d\TH:i:s\Z'),
            'receiptNumber' => $receipt->number,
            'uuid' => '',
            'previousUUID' => $previousUuid,
            'referenceOldUUID' => $referenceOldUuid ?? '',
        ];
        if ($receipt->isReturn()) {
            $header['referenceUUID'] = $referenceUuid;
        }
        $header += [
            'currency' => 'EGP',
            'exchangeRate' => 0,
            'sOrderNameCode' => '',
            'orderdeliveryMode' => '',
            'grossWeight' => 0,
            'netWeight' => 0,
        ];

        $document = [
            'header' => $header,
            'documentType' => [
                'receiptType' => $receipt->isReturn() ? 'R' : 'S',
                'typeVersion' => self::TYPE_VERSION,
            ],
            'seller' => [
                'rin' => (string) $eta->rin,
                'companyTradeName' => (string) $eta->company_trade_name,
                'branchCode' => $device->branch_code,
                'branchAddress' => $this->address($device),
                'deviceSerialNumber' => $device->serial,
                'activityCode' => (string) $eta->activity_code,
            ],
            'buyer' => $buyer,
            'itemData' => $items,
            'totalSales' => $this->num($totalSales),
            'totalCommercialDiscount' => $this->num($totalDiscount),
            'totalItemsDiscount' => 0,
            'netAmount' => $this->num($receipt->subtotal->minus($receipt->discount_total)),
            'feesAmount' => 0,
            'totalAmount' => $this->num($receipt->total),
            'taxTotals' => array_map(
                fn (string $type, BigDecimal $amount) => ['taxType' => $type, 'amount' => $this->num($amount)],
                array_keys($taxTotals),
                array_values($taxTotals),
            ),
            'paymentMethod' => $this->paymentMethod($receipt),
            'adjustment' => 0,
        ];

        $document['header']['uuid'] = $this->serializer->uuid($document);

        return $document;
    }

    /**
     * @param  string[]  $errors
     * @return array{data: array<string, mixed>, total_sale: BigDecimal, discount: BigDecimal, tax_amount: BigDecimal}|null
     */
    private function item(ReceiptLine $line, EtaSetting $eta, array &$errors): ?array
    {
        $product = $line->product;
        $code = EtaItemCode::firstWhere('product_id', $product->id);
        $unit = EtaUnitCode::firstWhere('unit_id', $line->unit_id);

        if ($code === null) {
            $errors[] = __('egypttax::receipts.errors.item_code', ['product' => $product->sku]);
        }
        if ($unit === null) {
            $errors[] = __('egypttax::receipts.errors.unit_code', ['unit' => $line->unit->name]);
        }

        $taxable = $this->taxableItem($line, $eta, $errors);

        if ($code === null || $unit === null || $taxable === null) {
            return null;
        }

        // Exact quantity × price; the rounding to the currency goes into the discount so netSale stays the line's net.
        $totalSale = $line->quantity->multipliedBy($line->unit_price)->toScale(5, RoundingMode::HalfUp);
        $discount = $totalSale->minus($line->net);

        $data = [
            'internalCode' => $product->sku,
            'description' => $product->getTranslation('name', 'ar', true) ?: $product->sku,
            'itemType' => $code->code_type,
            'itemCode' => $code->item_code,
            'unitType' => $unit->unit_type,
            'quantity' => $this->num($line->quantity),
            'unitPrice' => $this->num($line->unit_price),
            'netSale' => $this->num($line->net),
            'totalSale' => $this->num($totalSale),
            'total' => $this->num($line->line_total),
        ];
        if (! $discount->isZero()) {
            $data['commercialDiscountData'] = [['amount' => $this->num($discount), 'description' => 'Discount']];
        }
        $data['valueDifference'] = 0;
        $data['taxableItems'] = [$taxable];

        return ['data' => $data, 'total_sale' => $totalSale, 'discount' => $discount, 'tax_amount' => $line->tax_amount];
    }

    /**
     * @param  string[]  $errors
     * @return array<string, mixed>|null
     */
    private function taxableItem(ReceiptLine $line, EtaSetting $eta, array &$errors): ?array
    {
        if ($line->tax_id === null) {
            return ['taxType' => $eta->exempt_tax_type, 'amount' => 0, 'subType' => $eta->exempt_sub_type, 'rate' => 0];
        }

        $code = EtaTaxCode::firstWhere('tax_id', $line->tax_id);
        if ($code === null) {
            $errors[] = __('egypttax::receipts.errors.tax_code', ['tax' => $line->tax->code]);

            return null;
        }

        return [
            'taxType' => $code->tax_type,
            'amount' => $this->num($line->tax_amount),
            'subType' => $code->sub_type,
            'rate' => $line->tax->type === TaxType::Percent ? $this->num($line->tax_rate ?? BigDecimal::zero()) : 0,
        ];
    }

    /**
     * Walk-in customers stay anonymous; a company must give its tax number, and a person their
     * national id from the threshold on.
     *
     * @param  string[]  $errors
     * @return array<string, string>
     */
    private function buyer(Receipt $receipt, array &$errors): array
    {
        $partner = $receipt->partner;
        $required = $receipt->total->isGreaterThanOrEqualTo(config('egypttax.buyer_id_threshold'));

        if ($partner->id === (int) $this->settings->get('sales.walk_in_partner_id')) {
            if ($required) {
                $errors[] = __('egypttax::receipts.errors.buyer_required');
            }

            return ['type' => 'P', 'id' => '', 'name' => '', 'mobileNumber' => '', 'paymentNumber' => ''];
        }

        $isCompany = $partner->type === PartnerType::Company;
        $id = (string) ($isCompany ? $partner->tax_number : $partner->national_id);

        if ($id === '' && ($isCompany || $required)) {
            $errors[] = __($isCompany ? 'egypttax::receipts.errors.buyer_tax_number' : 'egypttax::receipts.errors.buyer_national_id', ['partner' => $partner->name]);
        }

        return [
            'type' => $isCompany ? 'B' : 'P',
            'id' => $id,
            'name' => $partner->name,
            'mobileNumber' => (string) $partner->phone,
            'paymentNumber' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function address(EtaDevice $device): array
    {
        $address = [];
        foreach (EtaDevice::ADDRESS_FIELDS as $field) {
            $address[$field] = (string) ($device->address[$field] ?? '');
        }

        return $address;
    }

    /**
     * The method that paid the most: C cash, V card, O anything else (or on account).
     */
    private function paymentMethod(Receipt $receipt): string
    {
        $main = $receipt->payments->sort(fn ($a, $b) => $b->amount->compareTo($a->amount))->first();

        return match ($main?->method->type) {
            PaymentMethodType::Cash => 'C',
            PaymentMethodType::Card => 'V',
            default => 'O',
        };
    }

    /**
     * Up to 5 decimals, no trailing zeros: a whole number is sent as an integer.
     */
    private function num(BigDecimal|string $value): int|float
    {
        $decimal = BigDecimal::of($value)->toScale(5, RoundingMode::HalfUp)->strippedOfTrailingZeros();

        return $decimal->getScale() === 0 ? (int) (string) $decimal : (float) (string) $decimal;
    }
}
