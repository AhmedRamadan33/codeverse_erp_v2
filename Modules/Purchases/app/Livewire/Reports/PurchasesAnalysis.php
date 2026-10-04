<?php

namespace Modules\Purchases\Livewire\Reports;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Documents\DocumentStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Partner;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductCategory;

/**
 * Net purchases (after returns, base currency) by product, supplier, category or day.
 */
#[Layout('core::layouts.app')]
class PurchasesAnalysis extends Component
{
    public const GROUPS = ['product', 'supplier', 'category', 'day'];

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $branchId = null;

    #[Url]
    public string $groupBy = 'product';

    public function mount(): void
    {
        Gate::authorize('purchases.reports.view');

        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
        $this->groupBy = in_array($this->groupBy, self::GROUPS, true) ? $this->groupBy : 'product';
    }

    private function rows(?int $branchId): Collection
    {
        $lines = fn (string $lineTable, string $docTable, string $fk, int $sign) => DB::table("{$lineTable} as l")
            ->join("{$docTable} as d", 'd.id', '=', "l.{$fk}")
            ->where('d.status', DocumentStatus::Posted->value)
            ->whereBetween('d.date', [$this->from, $this->to])
            ->when($branchId, fn ($q) => $q->where('d.branch_id', $branchId))
            ->selectRaw("d.date, d.partner_id, l.product_id, l.base_quantity * {$sign} as quantity, round(l.net * d.exchange_rate, 4) * {$sign} as net");

        $union = $lines('purchase_invoice_lines', 'purchase_invoices', 'purchase_invoice_id', 1)
            ->unionAll($lines('purchase_return_lines', 'purchase_returns', 'purchase_return_id', -1));

        $key = match ($this->groupBy) {
            'supplier' => 's.partner_id',
            'category' => 'p.category_id',
            'day' => 's.date',
            default => 's.product_id',
        };

        return DB::query()->fromSub($union, 's')
            ->when($this->groupBy === 'category', fn ($q) => $q->join('products as p', 'p.id', '=', 's.product_id'))
            ->groupBy(DB::raw($key))
            ->selectRaw("{$key} as `key`, sum(s.quantity) as quantity, sum(s.net) as net")
            ->orderBy($this->groupBy === 'day' ? 'key' : 'net', $this->groupBy === 'day' ? 'asc' : 'desc')
            ->get();
    }

    /**
     * @return array<string|int, string>
     */
    private function labels(Collection $rows): array
    {
        $keys = $rows->pluck('key')->filter()->all();

        return match ($this->groupBy) {
            'product' => Product::whereKey($keys)->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->label()])->all(),
            'supplier' => Partner::whereKey($keys)->pluck('name', 'id')->all(),
            'category' => ProductCategory::whereKey($keys)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            default => array_combine($keys, $keys) ?: [],
        };
    }

    public function render()
    {
        $this->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $user = auth()->user();
        abort_if($this->branchId && ! $user->canAccessBranch($this->branchId), 403);
        $branchId = $this->branchId ?? ($user->can('core.branches.all_access') ? null : $user->branches()->value('branches.id'));

        $rows = $this->rows($branchId);

        return view('purchases::livewire.reports.purchases-analysis', [
            'rows' => $rows,
            'labels' => $this->labels($rows),
            'total' => $rows->reduce(fn (BigDecimal $c, $r) => $c->plus(BigDecimal::of($r->net ?? 0)), BigDecimal::zero()),
            'groups' => self::GROUPS,
            'branches' => $user->can('core.branches.all_access') ? Branch::orderBy('code')->get() : $user->branches,
        ])->title(__('purchases::reports.title'));
    }
}
