<?php

namespace Modules\Purchases\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Purchases\Models\PurchaseDocument;
use Modules\Purchases\Models\PurchaseInvoice;
use Modules\Purchases\Models\PurchaseReturn;

/**
 * A4 prints of purchase documents; the page has its own print button.
 */
class PrintController
{
    public function invoice(int $id): View
    {
        Gate::authorize('purchases.invoices.view');

        return $this->render(PurchaseInvoice::with(['lines.product', 'lines.unit', 'partner', 'warehouse', 'currency'])->findOrFail($id), 'invoice');
    }

    public function return(int $id): View
    {
        Gate::authorize('purchases.returns.view');

        return $this->render(PurchaseReturn::with(['lines.product', 'lines.unit', 'partner', 'warehouse', 'currency', 'invoice'])->findOrFail($id), 'return');
    }

    private function render(PurchaseDocument $document, string $kind): View
    {
        abort_unless(auth()->user()->canAccessBranch($document->branch_id), 404);

        return view('purchases::print.document', [
            'document' => $document,
            'kind' => $kind,
            'scale' => $document->currency->decimal_places,
        ]);
    }
}
