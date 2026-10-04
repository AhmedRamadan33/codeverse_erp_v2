<?php

namespace Modules\Sales\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Sales\Models\SalesDocument;
use Modules\Sales\Models\SalesInvoice;
use Modules\Sales\Models\SalesReturn;

/**
 * A4 prints of sales documents; the page has its own print button.
 */
class PrintController
{
    public function invoice(int $id): View
    {
        Gate::authorize('sales.invoices.view');

        return $this->render(SalesInvoice::with(['lines.product', 'lines.unit', 'partner', 'warehouse', 'currency', 'priceList', 'paymentMethod'])->findOrFail($id), 'invoice');
    }

    public function return(int $id): View
    {
        Gate::authorize('sales.returns.view');

        return $this->render(SalesReturn::with(['lines.product', 'lines.unit', 'partner', 'warehouse', 'currency', 'invoice'])->findOrFail($id), 'return');
    }

    private function render(SalesDocument $document, string $kind): View
    {
        abort_unless(auth()->user()->canAccessBranch($document->branch_id), 404);

        return view('sales::print.document', [
            'document' => $document,
            'kind' => $kind,
            'scale' => $document->currency->decimal_places,
        ]);
    }
}
