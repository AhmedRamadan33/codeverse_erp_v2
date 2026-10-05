<?php

namespace Modules\Pos\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Currencies\Currencies;
use Modules\Core\Settings\Settings;
use Modules\Pos\Models\Receipt;
use Modules\Pos\Printing\ReceiptPrintExtras;

/**
 * An 80 mm receipt for a thermal printer; the page prints itself.
 */
class ReceiptPrintController
{
    public function __invoke(int $id, Settings $settings, Currencies $currencies, ReceiptPrintExtras $extras): View
    {
        $receipt = Receipt::with(['lines.product', 'lines.unit', 'payments.method', 'partner', 'register', 'branch', 'creator', 'original'])->findOrFail($id);

        // The cashier prints what they just sold; anyone else needs to see receipts.
        if ($receipt->created_by !== auth()->id()) {
            Gate::authorize('pos.receipts.view');
        }
        abort_unless($receipt->number !== null && auth()->user()->canAccessBranch($receipt->branch_id), 404);

        return view('pos::print.receipt', [
            'receipt' => $receipt,
            'company' => $settings->get('core.company_name') ?? config('app.name'),
            'taxNumber' => $settings->get('core.company_tax_number'),
            'phone' => $settings->get('core.company_phone'),
            'scale' => $currencies->base()->decimal_places,
            'currency' => $currencies->base()->code,
            'extras' => $extras->render($receipt),
        ]);
    }
}
